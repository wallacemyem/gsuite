<?php

namespace BrickServers\GoogleWorkspace;

class GoogleWorkspace
{
    public function __construct(
        private readonly Services\GoogleServicesFactory $services,
        private readonly Contracts\UsersRepositoryContract $users,
        private readonly Contracts\GroupsRepositoryContract $groups,
    ) {}

    public function users(): Contracts\UsersRepositoryContract
    {
        return $this->users;
    }

    public function groups(): Contracts\GroupsRepositoryContract
    {
        return $this->groups;
    }

    public function batch(): Utilities\BatchOperations
    {
        return app(Utilities\BatchOperations::class);
    }

    public function services(): Services\GoogleServicesFactory
    {
        return $this->services;
    }
}
