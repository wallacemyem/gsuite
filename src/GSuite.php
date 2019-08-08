<?php

namespace Wyattcast44\GSuite;

use Wyattcast44\GSuite\Services\Accounts\AccountsRepo;

class GSuite
{
    /**
     * The Google Client
     */
    public $google_client;

    /**
     * Bootstrap the service
     * @return $this
     */
    public function __construct()
    {
        $this->setGoogleClient();

        return $this;
    }

    /**
     * Set the $google_client
     * @return $this
     */
    protected function setGoogleClient()
    {
        if (!getenv('GOOGLE_APPLICATION_CREDENTIALS')) {
            putenv('GOOGLE_APPLICATION_CREDENTIALS=' . config('gsuite.credentials_path'));
        }

        $this->google_client = tap(new \Google_Client, function ($google_client) {
            $google_client->useApplicationDefaultCredentials();
            $google_client->setSubject(config('gsuite.subject'));
            $google_client->setScopes(config('gsuite.scopes'));
        });

        return $this;
    }

    public function accounts(AccountsRepo $accounts_repo)
    {
        return $accounts_repo;
    }
}
