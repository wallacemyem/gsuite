<?php

namespace Wyattcast44\GSuite\Services\Accounts;

use Wyattcast44\GSuite\Clients\GoogleServicesClient;
use Wyattcast44\GSuite\Contracts\AccountsRepoContract;

class AccountsRepo implements AccountsRepoContract
{
    protected $client;

    public function __construct(GoogleServicesClient $services)
    {
        $this->client = $services->getService('users');

        return $this;
    }

    public function getClient()
    {
        return $this->client;
    }

    public function delete(string $userKey)
    {
        //
    }
    
    // $parameters = [
    //     'userKey',
    //     'customFieldMask',
    //     'projection',
    //     'viewType'
    // ];
    
    public function get()
    {
        //
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
