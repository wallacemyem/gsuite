<?php

namespace Wyattcast44\GSuite\Contracts;

interface AccountsRepoContract
{
    public function delete(string $userKey);
    
    public function get();
    
    // $parameters = [
    //     'userKey',
    //     'customFieldMask',
    //     'projection',
    //     'viewType'
    // ];

    public function insert();

    public function list(array $parameters);
    
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

    public function makeAdmin(string $userKey);

    public function update(string $userKey);
}
