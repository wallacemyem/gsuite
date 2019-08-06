<?php

namespace Wyattcast44\GSuite;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class GSuiteAccountsRepository
{
    /**
     * GSuite Directory Accounts Client
     * \Google_Service_Directory_Resource_Users
     */
    protected $accounts_client;

    /**
     * GSuite account user first name max length
     * @link https://developers.google.com/admin-sdk/directory/v1/reference/users/insert
     */
    const MAX_FIRST_NAME_LENGTH = 60;

    /**
     * GSuite account user last name max length
     * @link https://developers.google.com/admin-sdk/directory/v1/reference/users/insert
     */
    const MAX_LAST_NAME_LENGTH = 60;

    /**
     * Boostrap the repo service
     * @return Wyattcast44\GSuite\GSuiteAccountsRepository
     * @link https://developers.google.com/admin-sdk/directory/v1/reference/users
     */
    public function __construct(GSuiteDirectory $directory_client)
    {
        $this->accounts_client = $directory_client->users;

        return $this;
    }

    /**
     * Delete a GSuite Account
     * @link https://developers.google.com/admin-sdk/directory/v1/reference/users/delete
     * @return bool
     */
    public function delete(string $email)
    {
        try {
            $status = $this->accounts_client->delete($email);
        } catch (\Exception $e) {
            throw new Exception("Error deleting account with email: {$email}.", 1);
        }

        return ($status->getStatusCode() == 204) ? true : false;
    }

    /**
     * Fetch a GSuite account by email
     * @return \Google_Service_Directory_User
     * @param $projection | Options: basic, custom, full | Default: full
     * @link https://developers.google.com/admin-sdk/directory/v1/reference/users/get
     */
    public function get(string $email, string $projection = 'full')
    {
        try {
            $account = $this->accounts_client->get($email, ['projection' => $projection]);
        } catch (\Exception $e) {
            throw new Exception("Error retriving account with email: {$email}", 1);
        }

        return $account;
    }

    /**
     * Create a new GSuite account
     * @link https://developers.google.com/admin-sdk/directory/v1/reference/users/insert
     * @param array $name | Should contain: first_name, last_name
     * @param string $email | The desired email address for the new account, ex joe@email.com
     * @param string $password | The desired default password
     * @return \GoogleServiceUser
     */
    public function insert(array $name, string $email, string $password)
    {
        /**
         * Ensure $name has proper keys
         */
        if (!Arr::has($name, ['first_name', 'last_name'])) {
            throw new \Exception("Name parameter must contain the following items: first_name, last_name", 1);
        }

        /**
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
        $google_user = tap(new \GoogleServiceUser, function ($google_user) use ($directory_name, $email, $password) {
            $google_user->setName($directory_name);
            $google_user->setPrimaryEmail($email);
            $google_user->setPassword($password);
            $google_user->setChangePasswordAtNextLogin(true);
        });

        /**
         * Attempt to actually create the new GSuite account
         */
        try {
            $account = $this->accounts_client->insert($google_user);
        } catch (\Exception $e) {
            throw new \Exception("Error Processing Request", 1);
        }

        return $account;
    }

    /**
     * List the GSuite accounts in your domain
     * @link https://developers.google.com/admin-sdk/directory/v1/reference/users/list
     */
    public function list()
    {
        if (!$this->shouldCache()) {
            $accounts = $this->accounts_client->listUsers(['domain' => config('gsuite.domain')])->users;

            return $accounts;
        }

        if (Cache::has(config('gsuite.cache.accounts.key'))) {
            $accounts = Cache::get('gsuite.cache.accounts.key');
        } else {
            $accounts = $this->accounts_client->listUsers(['domain' => config('gsuite.domain')])->users;

            Cache::add(config('gsuite.cache.accounts.key'), $accounts, config('gsuite.cache.accounts.cache-time'));
        }

        return $accounts;
    }

    public function makeAdmin(string $email)
    {
        //
    }

    public function update(string $email)
    {
        //
    }

    /**
     * @link https://developers.google.com/admin-sdk/directory/v1/guides/search-users
     */
    public function search()
    {
        //
    }

    public function checkEmailAvailability(string $email)
    {
        return true;
    }

    /**
     * @return void
     */
    public function flushCache()
    {
        if ($this->shouldCache()) {
            Cache::forget(config('gsuite.cache.accounts.key'));
        }
    }

    public function shouldCache()
    {
        return config('gsuite.cache.accounts.should-cache');
    }
}
