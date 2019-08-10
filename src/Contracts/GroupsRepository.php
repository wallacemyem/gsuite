<?php

namespace Wyattcast44\GSuite\Contracts;

interface GroupsRepository
{
    public function delete(string $groupKey);
    
    public function get(string $groupKey);

    public function insert(string $email, string $name, string $description);

    // $params = [
    //     'customer',
    //     'domain',
    //     'maxResults',
    //     'orderBy',
    //     'pageToken',
    //     'query',
    //     'sortOrder',
    //     'userKey'
    // ];

    public function list();

    public function update(string $groupKey);
}
