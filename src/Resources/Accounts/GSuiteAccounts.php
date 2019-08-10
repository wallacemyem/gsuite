<?php

namespace Wyattcast44\GSuite\Resources\Accounts;

class GSuiteAccounts
{
    /**
     * The accounts repository
     */
    protected $repository;

    public function __construct(AccountsRepository $repository)
    {
        $this->repository = $repository;
    }

    public function all()
    {
        return collect($this->repository->list()->users)->map(function ($account) {
            return new GSuiteAccount($account);
        });
    }

    public function create(array $name, string $email, string $password)
    {
        return new GSuiteAccount($this->repository->create($name, $email, $password));
    }

    /**
     * Get a single GSuite account
     */
    public function get(string $email)
    {
        $account = $this->repository->get($email);

        return new GSuiteAccount($account);
    }

    /**
     * Delete a single GSuite account
     */
    public function delete(string $email)
    {
        return $this->repository->delete($email);
    }

    /**
     * Get the client for user accounts
     */
    public function getClient()
    {
        return $this->repository;
    }
}
