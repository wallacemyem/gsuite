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
        return $this->repo->insert($email, $name, $description);
    }

    /**
     * Get a single G-Suite group
     */
    public function get(string $email)
    {
        return $this->repo->get($email, true);
    }

    /**
     * Delete a single G-Suite group
     */
    public function delete(string $email)
    {
        return $this->repo->delete($email);
    }

    /**
     * Get the client for groups
     */
    public function getClient()
    {
        return $this->repo;
    }
}
