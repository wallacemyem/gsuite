<?php

namespace BrickServers\GoogleWorkspace\Facades;

use Illuminate\Support\Facades\Facade;
use BrickServers\GoogleWorkspace\Contracts\UsersRepositoryContract;
use BrickServers\GoogleWorkspace\Contracts\GroupsRepositoryContract;
use BrickServers\GoogleWorkspace\Services\GoogleServicesFactory;

/**
 * @method static UsersRepositoryContract users()
 * @method static GroupsRepositoryContract groups()
 * @method static \BrickServers\GoogleWorkspace\Utilities\BatchOperations batch()
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
