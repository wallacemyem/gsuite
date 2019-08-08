<?php

namespace Wyattcast44\GSuite\Resources\Accounts;

use Wyattcast44\GSuite\Traits\CachesResults;
use Wyattcast44\GSuite\Clients\GoogleServicesClient;
use Wyattcast44\GSuite\Contracts\AccountsRepoContract;

class AccountsRepo implements AccountsRepoContract
{
    use CachesResults;

    /**
     * The accounts client
     *
     * \Google_Service_Directory_Resource_Users
     */
    protected $client;

    /**
     * G-Suite account user first name max length
     *
     * @link https://developers.google.com/admin-sdk/directory/v1/reference/users/insert
     */
    const MAX_FIRST_NAME_LENGTH = 60;

    /**
     * G-Suite account user last name max length
     *
     * @link https://developers.google.com/admin-sdk/directory/v1/reference/users/insert
     */
    const MAX_LAST_NAME_LENGTH = 60;

    /**
     * Bootstrap the client
     *
     * @return self
     */
    public function __construct(GoogleServicesClient $services)
    {
        $this->client = $services->getService('users');

        return $this;
    }

    /**
     * Get the configured client
     *
     * @return \Google_Service_Directory_Resource_Users
     */
    public function getClient()
    {
        return $this->client;
    }

    public function shouldCache()
    {
        return config('gsuite.cache.accounts.should-cache');
    }

    /**
     * Delete an account
     *
     * @link https://developers.google.com/admin-sdk/directory/v1/reference/users/delete
     *
     * @param string $userKey | The email address of the account to delete
     * @return bool
     */
    public function delete(string $userKey)
    {
        try {
            $response = $this->client->delete($userKey);
        } catch (\Exception $e) {
            throw new \Exception("Error deleting account with email: {$userKey}.", 1);
        }

        return ($response->getStatusCode() == 204) ? true : false;
    }

    /**
     * Get an G-Suite account associated by email address, alias, or unique user key
     *
     * @link https://developers.google.com/admin-sdk/directory/v1/reference/users/get
     *
     * @param string $userKey | The accounts primary email address, an alias email, or unique user id
     * @param string $projection | Options: basic, custom, full, Default: full
     * @param string $viewType | Options: admin_view, domain_public, Default: admin_view
     * @return \Google_Service_Directory_User
     */
    public function get(string $userKey, string $projection = 'full', string $viewType = 'admin_view', $customFieldMask = '')
    {
        if ($projection === 'custom' && $customFieldMask === '') {
            throw new \Exception("Error retriving account for user key: {$userKey}, when using {$projection}, ensure you set the customFieldMask parameter.", 1);
        }

        try {
            if ($this->shouldCache() && $this->checkCache(config('gsuite.cache.accounts.key') . ':' . $userKey)) {
                $account = $this->getCache(config('gsuite.cache.accounts.key') . ':' . $userKey);
            } else {
                $account = $this->client->get($userKey, [
                    'projection' => $projection,
                    'viewType' => $viewType,
                ]);

                if ($this->shouldCache()) {
                    $this->putCache(config('gsuite.cache.accounts.key') . ':' . $userKey, $account, config('gsuite.cache.accounts.cache-time'));
                }
            }
        } catch (\Exception $e) {
            throw new \Exception("Error retriving account.", 1);
        }

        return $account;
    }

    /**
     * Create a new G-Suite account
     *
     * @link https://developers.google.com/admin-sdk/directory/v1/reference/users/insert
     *
     * @param array $name | Should contain: first_name, last_name
     * @param string $email | The desired email address for the new account, ex joe@email.com
     * @param string $password | The desired default password
     * @param bool $changePasswordNextLogin | Default true
     * @return \GoogleServiceUser
     */
    public function insert(array $name, string $email, string $password, bool $changePasswordNextLogin = true)
    {
        /**
         * Ensure $name has proper keys
         */
        if (!Arr::has($name, ['first_name', 'last_name'])) {
            throw new \Exception("Name parameter must contain the following items: first_name, last_name", 1);
        }

        /**;
         * Ensure names meet max length requirement
         */
        if (strlen($name['first_name']) > self::MAX_FIRST_NAME_LENGTH || strlen($name['last_name']) > self::MAX_LAST_NAME_LENGTH) {
            throw new \Exception("First and last name must be 60 characters or less", 1);
        }

        /**
         * Check email availability
         */
        if (!$this->checkEmailAvailability($email)) {
            throw new Exception("Email address already taken.", 1);
        }

        /**
         * Create and set the Google Directory Name
         */
        $directory_name = tap(new \Google_Service_Directory_UserName, function ($directory_name) use ($name) {
            $directory_name->setGivenName(ucfirst($name['first_name']));
            $directory_name->setFamilyName(ucfirst($name['last_name']));
        });

        /**
         * Create and configure the new Google User
         */
        $google_user = tap(new \GoogleServiceUser, function ($google_user) use ($directory_name, $email, $password, $changePasswordNextLogin) {
            $google_user->setName($directory_name);
            $google_user->setPrimaryEmail($email);
            $google_user->setPassword($password);
            $google_user->setChangePasswordAtNextLogin($changePasswordNextLogin);
        });

        /**
         * Attempt to actually create the new GSuite account
         */
        try {
            $account = $this->client->insert($google_user);
        } catch (\Exception $e) {
            throw $e;
        }

        return $account;
    }

    /**
     * Retrieve a list a created G-Suite accounts
     *
     * @link https://developers.google.com/admin-sdk/directory/v1/reference/users/list
     *
     * @param array $parameters
     * @return array
     */
    public function list(array $parameters = [])
    {
        $defaultParameters = [
            "domain" => config('gsuite.domain'),
        ];

        $parameters = array_merge($defaultParameters, $parameters);

        try {
            if ($this->shouldCache() && $this->checkCache(config('gsuite.cache.accounts.key'))) {
                $accounts = $this->getCache(config('gsuite.cache.accounts.key'));
            } else {
                $accounts = $this->client->listUsers($parameters);

                if ($this->shouldCache()) {
                    $this->putCache(config('gsuite.cache.accounts.key'), $accounts, config('gsuite.cache.accounts.cache-time'));
                }
            }
        } catch (\Exception $e) {
            throw $e;
        }

        return $accounts;
    }

    /**
     * Marks an account as a super-admin
     *
     * @link https://developers.google.com/admin-sdk/directory/v1/reference/users/makeAdmin
     *
     * @param string $userKey | The accounts primary email address, an alias email, or unique user id
     */
    public function makeAdmin(string $userKey)
    {
        try {
            $response = $this->client->makeAdmin($userKey);
        } catch (\Exception $e) {
            throw $e;
        }

        return ($response->getStatusCode() == 204) ? true : false;
    }

    /**
     * Updates an accounts information
     *
     * @todo Experiment with how this works
     *
     * @link https://developers.google.com/admin-sdk/directory/v1/reference/users/update
     *
     * @param string $userKey | The accounts primary email address, an alias email, or unique user id
     */
    public function update(string $userKey, array $parameters = [])
    {
        throw new \Exception("Error Processing Request", 1);
    }

    public function checkEmailAvailability(string $email)
    {
        return true;
    }
}
