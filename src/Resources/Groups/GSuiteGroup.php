<?php

namespace Wyattcast44\GSuite\Resources\Groups;

class GSuiteGroup
{
    protected $repo;

    public function __construct(GroupsRepo $repo)
    {
        $this->repo = $repo;
    }

    /**
     * Return a collection of all the groups in your domain
     */
    public function all()
    {
        return collect($this->repo->list());
    }

    /**
     * Create a new G-Suite group
     */
    public function create(string $email, string $name = '', string $description = '')
    {
        return $this->repo->create($email, $name, $description);
    }

    /**
     * Get a single GSuite account
     */
    public function get(string $email)
    {
        return $this->repo->get($email);
    }

    /**
     * Delete a single GSuite account
     */
    public function delete(string $email)
    {
        return $this->repo->delete($email);
    }

    /**
     * Get the client for user accounts
     */
    public function getClient()
    {
        return $this->accounts_repo;
    }
}
