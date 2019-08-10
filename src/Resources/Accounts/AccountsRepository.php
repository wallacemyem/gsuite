<?php

namespace Wyattcast44\GSuite\Resources\Accounts;

use Wyattcast44\GSuite\Traits\CachesResults;
use Wyattcast44\GSuite\Clients\GoogleServicesClient;
use Wyattcast44\GSuite\Contracts\AccountsRepository as AccountsRepositoryContract;
use Illuminate\Support\Arr;

class AccountsRepository implements AccountsRepositoryContract
{
    use CachesResults;

    /**
     * The accounts client
     *
     * @see \Google_Service_Directory_Resource_Users
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
     * G-Suite password min length
     *
     * @link https://developers.google.com/admin-sdk/directory/v1/reference/users/insert
     */
    const MIN_PASSWORD_LENGTH = 8;

    /**
     * G-Suite password max length
     *
     * @link https://developers.google.com/admin-sdk/directory/v1/reference/users/insert
     */
    const MAX_PASSWORD_LENGTH = 100;

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
     * Delete a G-Suite account
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
            throw $e;
        }

        return ($response->getStatusCode() == 204) ? true : false;
    }

    /**
     * Get an G-Suite account associated by email address, alias, or unique user key
     *
     * @link https://developers.google.com/admin-sdk/directory/v1/reference/users/get
     *
     * @param string $userKey | The accounts primary email address, an alias email, or unique user id
     * @param string $projection | Options: basic, full, Default: full
     * @param string $viewType | Options: admin_view, domain_public, Default: admin_view
     * @return \Google_Service_Directory_User
     */
    public function get(string $userKey, string $projection = 'full', string $viewType = 'admin_view')
    {
        if (!in_array($projection, array('basic', 'full'))) {
            throw new \Exception("The projection must be either 'basic' or 'full'.", 1);
        }

        if (!in_array($viewType, array('admin_view', 'domain_public'))) {
            throw new \Exception("The view type must be either 'admin_view' or 'domain_public'.", 1);
        }

        if ($this->shouldCache()) {
            if ($this->checkCache($this->getCacheKey($userKey))) {
                $account = $this->getCache($this->getCacheKey($userKey));
            } else {
                $account = $this->client->get($userKey, ['projection' => $projection, 'viewType' => $viewType]);

                $this->putCache($this->getCacheKey($userKey), $account);
            }
        } else {
            $account = $this->client->get($userKey, ['projection' => $projection, 'viewType' => $viewType]);
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
     * @return \Google_Service_Directory_User
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
         * Ensure password meets length requirements
         */
        if (strlen($password) < self::MIN_PASSWORD_LENGTH || strlen($password) > self::MAX_PASSWORD_LENGTH) {
            throw new \Exception("Password must be between 8 and 100 characters", 1);
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
        $google_user = tap(new \Google_Service_Directory_User, function ($google_user) use ($directory_name, $email, $password, $changePasswordNextLogin) {
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
     * Retrieve a list G-Suite accounts in your domain
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
            if ($this->shouldCache()) {
                if ($this->checkCache($this->getCacheKey())) {
                    $accounts = $this->getCache($this->getCacheKey());
                } else {
                    $accounts = $this->client->listUsers($parameters);
    
                    $this->putCache($this->getCacheKey(), $accounts);
                }
            } else {
                $accounts = $this->client->listUsers($parameters);
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

    /**
     * Suspend an account
     *
     * @return \Google_Service_Directory_User
     */
    public function suspend(string $userKey)
    {
        try {
            $account = $this->client->update($userKey, new \Google_Service_Directory_User(['suspended' => true]));
        } catch (\Exception $e) {
            throw $e;
        }

        return $account;
    }

    /**
     * Check the aviliablity of an email address
     *
     * @param $email
     * @return bool
     */
    public function checkEmailAvailability(string $email)
    {
        return true;
    }

    /**
     * Unsuspend an account
     *
     * @return \Google_Service_Directory_User
     */
    public function unsuspend(string $userKey)
    {
        try {
            $account = $this->client->update($userKey, new \Google_Service_Directory_User(['suspended' => false]));
        } catch (\Exception $e) {
            throw $e;
        }

        return $account;
    }

    /**
     * Get the configured client,
     * useful if you need to write any custom functionality
     *
     * @return \Google_Service_Directory_Resource_Users
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * Should the accounts be cached
     *
     * @return bool
     */
    protected function shouldCache()
    {
        return config('gsuite.cache.accounts.should-cache');
    }

    /**
     * Get the proper key for caching results
     *
     * @return string
     */
    protected function getCacheKey(string $userKey = null)
    {
        return config('gsuite.cache.accounts.key') . ($userKey) ? ':' . $userKey : '';
    }

    protected function getCacheTime()
    {
        return config('gsuite.cache.accounts.cache-time');
    }
}
