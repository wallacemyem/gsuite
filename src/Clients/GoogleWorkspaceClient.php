<?php

namespace BrickServers\GoogleWorkspace\Clients;

use Google\Client;
use BrickServers\GoogleWorkspace\Exceptions\GoogleWorkspaceException;
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
    ) {
        $this->logger = $logger ?? new NullLogger();
        $this->initialize();
    }

    private function initialize(): void
    {
        try {
            if (!file_exists($this->credentialsPath)) {
                throw GoogleWorkspaceException::missingCredentials();
            }

            $this->client = new Client();
            $this->client->setAuthConfig($this->credentialsPath);
            $this->client->setScopes($this->scopes);
            $this->client->setSubject($this->subject);

            $this->logger->debug('Google Workspace Client initialized');
        } catch (\Exception $e) {
            if ($e instanceof GoogleWorkspaceException) {
                throw $e;
            }
            throw GoogleWorkspaceException::invalidConfiguration(
                'Failed to initialize client: ' . $e->getMessage()
            );
        }
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
    ): self {
        return new self($credentialsPath, $subject, $scopes, $logger);
    }
}
