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
        return new GSuiteAccount($this->repository->insert($name, $email, $password));
    }

    public function get(string $email)
    {
        return new GSuiteAccount($this->repository->get($email));
    }

    public function delete(string $email)
    {
        return $this->repository->delete($email);
    }

    /**
     * Get the client for accounts
     *
     * @return \Google_Service_Directory_Resource_Users
     */
    public function getClient()
    {
        return $this->repository->getClient();
    }
}
