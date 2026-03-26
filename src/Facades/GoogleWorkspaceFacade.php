<?php

namespace BrickServers\GoogleWorkspace\Facades;

use Illuminate\Support\Facades\Facade;
use BrickServers\GoogleWorkspace\Repositories\UsersRepository;
use BrickServers\GoogleWorkspace\Repositories\GroupsRepository;
use BrickServers\GoogleWorkspace\Services\GoogleServicesFactory;

/**
 * @method static UsersRepository users()
 * @method static GroupsRepository groups()
 * @method static GoogleServicesFactory services()
 * 
 * @see \BrickServers\GoogleWorkspace\GoogleWorkspace
 */
class GoogleWorkspaceFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'google-workspace';
    }
}
