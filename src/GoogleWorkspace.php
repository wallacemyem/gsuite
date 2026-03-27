<?php

namespace BrickServers\GoogleWorkspace;

class GoogleWorkspace
{
    public function __construct(
        private readonly Services\GoogleServicesFactory $services,
        private readonly Repositories\UsersRepository $users,
        private readonly Repositories\GroupsRepository $groups,
    ) {}

    public function users(): Repositories\UsersRepository
    {
        return $this->users;
    }

    public function groups(): Repositories\GroupsRepository
    {
        return $this->groups;
    }

    public function services(): Services\GoogleServicesFactory
    {
        return $this->services;
    }
}
