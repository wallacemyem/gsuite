<?php

namespace Wyattcast44\GSuite\Resources\Accounts;

class GSuiteAccount
{
    protected $accounts_repo;

    public function __construct(AccountsRepo $repo)
    {
        $this->accounts_repo = $repo;
    }

    /**
     * Return a collection of all the accounts in your domain
     */
    public function all()
    {
        return collect($this->accounts_repo->list()->users);
    }

    /**
     * Create a new GSuite account
     */
    public function create(array $name, string $email, string $password)
    {
        return $this->accounts_repo->create($name, $email, $password);
    }

    /**
     * Get a single GSuite account
     */
    public function get(string $email)
    {
        return $this->accounts_repo->get($email);
    }

    /**
     * Delete a single GSuite account
     */
    public function delete(string $email)
    {
        return $this->accounts_repo->delete($email);
    }

    /**
     * Get the client for user accounts
     */
    public function getClient()
    {
        return $this->accounts_repo;
    }
}
