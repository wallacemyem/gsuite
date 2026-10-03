<?php

namespace BrickServers\GoogleWorkspace\Clients;

use BrickServers\GoogleWorkspace\Exceptions\GoogleWorkspaceException;
use Google\Client;
use Google\Task\Runner;
use GuzzleHttp\Client as HttpClient;
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
     * Retry transient failures (5xx, rate limits, network errors) with exponential backoff.
     */
    private function configureRetries(): void
    {
        $maxAttempts = max(1, (int) ($this->retry['max_attempts'] ?? 1));

        $this->client->setConfig('retry', [
            'retries' => $maxAttempts - 1,
            'initial_delay' => max(0, (int) ($this->retry['delay_ms'] ?? 100)) / 1000,
        ]);
        $this->client->setConfig('retry_map', [
            '429' => Runner::TASK_RETRY_ALWAYS,
            '500' => Runner::TASK_RETRY_ALWAYS,
            '502' => Runner::TASK_RETRY_ALWAYS,
            '503' => Runner::TASK_RETRY_ALWAYS,
            '504' => Runner::TASK_RETRY_ALWAYS,
            'rateLimitExceeded' => Runner::TASK_RETRY_ALWAYS,
            'userRateLimitExceeded' => Runner::TASK_RETRY_ALWAYS,
            6 => Runner::TASK_RETRY_ALWAYS,  // CURLE_COULDNT_RESOLVE_HOST
            7 => Runner::TASK_RETRY_ALWAYS,  // CURLE_COULDNT_CONNECT
            28 => Runner::TASK_RETRY_ALWAYS, // CURLE_OPERATION_TIMEOUTED
            35 => Runner::TASK_RETRY_ALWAYS, // CURLE_SSL_CONNECT_ERROR
            52 => Runner::TASK_RETRY_ALWAYS, // CURLE_GOT_NOTHING
        ]);
    }

    private function configureTimeouts(): void
    {
        // Mirrors Google\Client's default HTTP client, plus the configured timeouts
        $this->client->setHttpClient(new HttpClient([
            'base_uri' => $this->client->getConfig('base_path'),
            'http_errors' => false,
            'connect_timeout' => (float) ($this->timeouts['connect'] ?? 10),
            'timeout' => (float) ($this->timeouts['read'] ?? 60),
        ]));
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
