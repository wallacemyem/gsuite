<?php

namespace Wyattcast44\GSuite\Services\Accounts;

use Wyattcast44\GSuite\Clients\GoogleServicesClient;
use Wyattcast44\GSuite\Contracts\AccountsRepoContract;

class AccountsRepo implements AccountsRepoContract
{
    /**
     * The accounts client
     *
     * \Google_Service_Directory_Resource_Users
     */
    protected $client;

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
     * @param string $userKey | The accounts primary email address, an alias email, or unique user key
     * @param string $projection | Options: basic, custom, full, Default: full
     * @param string $viewType | Options: admin_view, domain_public, Default: admin_view
     * @return \Google_Service_Directory_User
     */
    public function get(string $userKey, string $projection = 'full', $viewType = 'admin_view', $customFieldMask = '')
    {
        if ($projection === 'custom' && $customFieldMask === '') {
            throw new \Exception("Error retriving account for user key: {$userKey}, when using {$projection}, ensure you set the customFieldMask parameter.", 1);
        }

        try {
            $account = $this->client->get($userKey, [
                'projection' => $projection,
                'viewType' => $viewType,
            ]);
        } catch (\Exception $e) {
            throw new \Exception("Error retriving account.", 1);
        }

        return $account;
    }

    public function insert()
    {
        //
    }

    // $parameters = [
    //     "customFieldMask",
    //     "customer",
    //     "domain",
    //     "event",
    //     "maxResults",
    //     "orderBy",
    //     "pageToken",
    //     "projection",
    //     "query",
    //     "showDeleted",
    //     "sortOrder",
    //     "viewType",
    // ];

    public function list(array $parameters)
    {
        //
    }

    public function makeAdmin(string $userKey)
    {
        //
    }

    public function update(string $userKey)
    {
        //
    }
}
