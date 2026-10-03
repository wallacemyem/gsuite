<?php

namespace BrickServers\GoogleWorkspace\Clients;

use BrickServers\GoogleWorkspace\Exceptions\GoogleWorkspaceException;
use Google\Client;
use Google\Task\Runner;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ConnectTimeoutException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Modern Google API Client wrapper
 */
class GoogleWorkspaceClient
{
    private Client $client;

    private LoggerInterface $logger;

    public function __construct(
        private readonly string $credentialsPath,
        private readonly string $subject,
        private readonly array $scopes = [],
        ?LoggerInterface $logger = null,
        private readonly array $retry = [],
        private readonly array $timeouts = [],
    ) {
        $this->logger = $logger ?? new NullLogger;
        $this->initialize();
    }

    private function initialize(): void
    {
        try {
            if (! file_exists($this->credentialsPath)) {
                throw GoogleWorkspaceException::missingCredentials();
            }

            if (trim($this->subject) === '') {
                throw GoogleWorkspaceException::invalidConfiguration(
                    'An admin subject (GOOGLE_WORKSPACE_SUBJECT) is required for domain-wide delegation'
                );
            }

            $this->client = new Client;
            $this->client->setAuthConfig($this->credentialsPath);
            $this->client->setScopes($this->scopes);
            $this->client->setSubject($this->subject);
            $this->configureRetries();
            $this->configureTimeouts();

            $this->logger->debug('Google Workspace Client initialized');
        } catch (\Exception $e) {
            if ($e instanceof GoogleWorkspaceException) {
                throw $e;
            }
            throw GoogleWorkspaceException::invalidConfiguration(
                'Failed to initialize client: '.$e->getMessage()
            );
        }
    }

    /**
     * Retry error responses (5xx, rate limits) with exponential backoff. Google's
     * retry runner only sees failures that produced an HTTP response; transport
     * failures are retried by the middleware added in configureTimeouts().
     */
    private function configureRetries(): void
    {
        $this->client->setConfig('retry', [
            'retries' => $this->maxAttempts() - 1,
            'initial_delay' => $this->delayMs() / 1000,
        ]);
        $this->client->setConfig('retry_map', [
            '429' => Runner::TASK_RETRY_ALWAYS,
            '500' => Runner::TASK_RETRY_ALWAYS,
            '502' => Runner::TASK_RETRY_ALWAYS,
            '503' => Runner::TASK_RETRY_ALWAYS,
            '504' => Runner::TASK_RETRY_ALWAYS,
            'rateLimitExceeded' => Runner::TASK_RETRY_ALWAYS,
            'userRateLimitExceeded' => Runner::TASK_RETRY_ALWAYS,
        ]);
    }

    private function configureTimeouts(): void
    {
        // Google\Client keeps this handler stack when it adds authentication
        $stack = HandlerStack::create();
        $stack->push(Middleware::retry($this->shouldRetryTransport(...), $this->transportRetryDelay(...)), 'retry_transport');

        // Mirrors Google\Client's default HTTP client, plus the configured timeouts
        $this->client->setHttpClient(new HttpClient([
            'handler' => $stack,
            'base_uri' => $this->client->getConfig('base_path'),
            'http_errors' => false,
            'connect_timeout' => (float) ($this->timeouts['connect'] ?? 10),
            'timeout' => (float) ($this->timeouts['read'] ?? 60),
        ]));
    }

    /**
     * HTTP methods that can be repeated without changing the result.
     */
    private const IDEMPOTENT_METHODS = ['GET', 'HEAD', 'OPTIONS', 'PUT', 'DELETE'];

    /**
     * cURL errors raised before the request is sent (DNS, proxy, TCP connect, TLS
     * handshake), so the request certainly never reached Google.
     */
    private const NOT_SENT_CURL_ERRORS = [5, 6, 7, 35, 51, 60, 83, 90, 91, 96, 97, 98, 101];

    /**
     * Retry when no HTTP response was received. Error responses are left to Google's
     * runner so they are never retried twice.
     *
     * A timeout or dropped connection may happen after Google has processed the
     * request, so non-idempotent requests (POST, PATCH: inserts, sends, moves) are
     * only retried when the connection was never established; otherwise a retry
     * could, for example, create a duplicate calendar event or send an email twice.
     */
    private function shouldRetryTransport(int $retries, RequestInterface $request, ?ResponseInterface $response = null, ?\Throwable $exception = null): bool
    {
        if ($retries >= $this->maxAttempts() - 1 || $response !== null || ! $exception instanceof TransferException) {
            return false;
        }

        // Guzzle 7 keeps the response on RequestException, Guzzle 8 only on its subclasses
        if (method_exists($exception, 'getResponse') && $exception->getResponse() !== null) {
            return false;
        }

        $retry = in_array(strtoupper($request->getMethod()), self::IDEMPOTENT_METHODS, true)
            || $this->neverSent($exception);

        if ($retry) {
            $this->logger->warning('Retrying Google API request after a network error', [
                'attempt' => $retries + 1,
                'method' => $request->getMethod(),
                'error' => $exception->getMessage(),
            ]);
        }

        return $retry;
    }

    /**
     * Whether the failure provably happened before the request was sent. Guzzle 7
     * reports read timeouts and empty replies as ConnectException too, so the
     * exception class alone is not enough: rely on the cURL error number.
     */
    private function neverSent(TransferException $exception): bool
    {
        // Guzzle 8 distinguishes a timeout while connecting from one while waiting for a reply
        if (class_exists(ConnectTimeoutException::class)
            && $exception instanceof ConnectTimeoutException) {
            return true;
        }

        $context = method_exists($exception, 'getHandlerContext') ? $exception->getHandlerContext() : [];
        $errno = $context['errno'] ?? (preg_match('/cURL error (\d+)/', $exception->getMessage(), $m) ? (int) $m[1] : null);

        return $errno !== null && in_array((int) $errno, self::NOT_SENT_CURL_ERRORS, true);
    }

    /**
     * Exponential backoff in milliseconds: delay_ms, 2 x delay_ms, 4 x delay_ms, ...
     */
    private function transportRetryDelay(int $retries): int
    {
        return $this->delayMs() * (2 ** max(0, $retries - 1));
    }

    private function maxAttempts(): int
    {
        return max(1, (int) ($this->retry['max_attempts'] ?? 1));
    }

    private function delayMs(): int
    {
        return max(0, (int) ($this->retry['delay_ms'] ?? 100));
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    /**
     * A client with the same credentials and settings that impersonates another
     * account (domain-wide delegation). Pass scopes to override the configured ones.
     */
    public function withSubject(string $subject, ?array $scopes = null): self
    {
        return new self(
            $this->credentialsPath,
            $subject,
            $scopes ?? $this->scopes,
            $this->logger,
            $this->retry,
            $this->timeouts,
        );
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public static function make(
        string $credentialsPath,
        string $subject,
        array $scopes = [],
        ?LoggerInterface $logger = null,
        array $retry = [],
        array $timeouts = [],
    ): self {
        return new self($credentialsPath, $subject, $scopes, $logger, $retry, $timeouts);
    }
}
