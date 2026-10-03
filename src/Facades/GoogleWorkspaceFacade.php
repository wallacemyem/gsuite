<?php

namespace BrickServers\GoogleWorkspace\Facades;

use BrickServers\GoogleWorkspace\Contracts\GroupsRepositoryContract;
use BrickServers\GoogleWorkspace\Contracts\UsersRepositoryContract;
use BrickServers\GoogleWorkspace\GoogleWorkspace;
use BrickServers\GoogleWorkspace\Services\GoogleServicesFactory;
use Illuminate\Support\Facades\Facade;

/**
 * @method static UsersRepositoryContract users()
 * @method static GroupsRepositoryContract groups()
 * @method static \BrickServers\GoogleWorkspace\Utilities\BatchOperations batch()
 * @method static GoogleServicesFactory services()
 *
 * @see GoogleWorkspace
 */
class GoogleWorkspaceFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'google-workspace';
    }
}
