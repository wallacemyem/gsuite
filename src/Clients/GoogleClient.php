<?php

namespace Wyattcast44\GSuite\Clients;

use Wyattcast44\GSuite\Contracts\ClientContract;

class GoogleClient implements ClientContract
{
    /**
     * The \Google_Client instance
     */
    protected $client;

    /**
     * Bootstrap the client
     *
     * @return self
     */
    public function __construct()
    {
        $this->setEnv()->setClient()->setScopes()->setSubject();

        return $this;
    }

    /**
     * Get the configured client
     *
     * @return \Google_Client
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * Set the base client
     *
     * @return self
     */
    public function setClient()
    {
        $this->client = tap(new \Google_Client, function ($client) {
            $client->useApplicationDefaultCredentials();
        });

        return $this;
    }

    /**
     * Set the env variables
     *
     * @return self
     */
    public function setEnv()
    {
        if (!getenv('GOOGLE_APPLICATION_CREDENTIALS')) {
            putenv('GOOGLE_APPLICATION_CREDENTIALS=' . config('gsuite.credentials_path'));
        }

        return $this;
    }

    /**
     * Set the requested scopes on the client
     *
     * @return self
     */
    public function setScopes(array $scopes = [])
    {
        if ($scopes === []) {
            $scopes = config('gsuite.scopes');
        }

        $this->client->setScopes($scopes);

        return $this;
    }

    /**
     * Set the subject on the client
     *
     * @return self
     */
    public function setSubject(string $subject = null)
    {
        if ($subject === null) {
            $subject = config('gsuite.subject');
        }

        $this->client->setSubject($subject);

        return $this;
    }
}