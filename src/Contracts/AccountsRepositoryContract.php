<?php

namespace Wyattcast44\GSuite\Contracts;

interface AccountsRepositoryContract
{
    public function list(array $parameters);

    public function delete(string $userKey);
    
    public function suspend(string $userKey);

    public function unsuspend(string $userKey);

    public function makeAdmin(string $userKey);

    public function get(string $userKey, string $projection, string $viewType);

    public function insert(array $name, string $email, string $password, bool $changePasswordNextLogin);
}
