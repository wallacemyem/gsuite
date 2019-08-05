<?php

use Illuminate\Support\Arr;
use Wyattcast44\GSuite\GSuiteAccountsRepository;

class GSuiteAccount
{
    protected $accounts_repo;

    public function __construct(GSuiteAccountsRepository $accounts_repo)
    {
        $this->accounts_repo = $accounts_repo;
    }

    /**
     * Fetch all GSuite accounts
     */
    public function all()
    {
        return $this->accounts_repo->fetchAll();
    }

    /**
     * Fetch a single GSuite account
     */
    public function find(string $email)
    {
        return $this->accounts_repo->fetch($email);
    }

    /**
     * @link https://developers.google.com/admin-sdk/directory/v1/reference/users
     */
    public function create(array $name, string $email, string $password = '')
    {
        //
    }
}
