<?php

namespace BrickServers\GoogleWorkspace\Services;

use Google\Service\Directory;
use Google\Service\Classroom;
use Google\Service\Calendar;
use Google\Service\Gmail;
use Google\Service\Drive;
use BrickServers\GoogleWorkspace\Clients\GoogleWorkspaceClient;
use BrickServers\GoogleWorkspace\Exceptions\GoogleWorkspaceException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Google Services Factory
 * 
 * Manages all Google API service instances
 */
class GoogleServicesFactory
{
    private array $services = [];
    private LoggerInterface $logger;

    public function __construct(
        private readonly GoogleWorkspaceClient $client,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Get the Directory service (Admin SDK)
     */
    public function directory(): Directory
    {
        if (!isset($this->services['directory'])) {
            try {
                $this->services['directory'] = new Directory($this->client->getClient());
                $this->logger->debug('Directory service initialized');
            } catch (\Exception $e) {
                throw GoogleWorkspaceException::apiError('Failed to initialize Directory service: ' . $e->getMessage(), $e);
            }
        }

        return $this->services['directory'];
    }

    /**
     * Get the Classroom service
     */
    public function classroom(): Classroom
    {
        if (!isset($this->services['classroom'])) {
            try {
                $this->services['classroom'] = new Classroom($this->client->getClient());
                $this->logger->debug('Classroom service initialized');
            } catch (\Exception $e) {
                throw GoogleWorkspaceException::apiError('Failed to initialize Classroom service: ' . $e->getMessage(), $e);
            }
        }

        return $this->services['classroom'];
    }

    /**
     * Get the Calendar service
     */
    public function calendar(): Calendar
    {
        if (!isset($this->services['calendar'])) {
            try {
                $this->services['calendar'] = new Calendar($this->client->getClient());
                $this->logger->debug('Calendar service initialized');
            } catch (\Exception $e) {
                throw GoogleWorkspaceException::apiError('Failed to initialize Calendar service: ' . $e->getMessage(), $e);
            }
        }

        return $this->services['calendar'];
    }

    /**
     * Get the Gmail service
     */
    public function gmail(): Gmail
    {
        if (!isset($this->services['gmail'])) {
            try {
                $this->services['gmail'] = new Gmail($this->client->getClient());
                $this->logger->debug('Gmail service initialized');
            } catch (\Exception $e) {
                throw GoogleWorkspaceException::apiError('Failed to initialize Gmail service: ' . $e->getMessage(), $e);
            }
        }

        return $this->services['gmail'];
    }

    /**
     * Get the Drive service
     */
    public function drive(): Drive
    {
        if (!isset($this->services['drive'])) {
            try {
                $this->services['drive'] = new Drive($this->client->getClient());
                $this->logger->debug('Drive service initialized');
            } catch (\Exception $e) {
                throw GoogleWorkspaceException::apiError('Failed to initialize Drive service: ' . $e->getMessage(), $e);
            }
        }

        return $this->services['drive'];
    }

    /**
     * Refresh all cached services
     */
    public function refresh(): void
    {
        $this->services = [];
    }
}
