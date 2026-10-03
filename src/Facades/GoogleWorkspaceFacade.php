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
 * @method static \BrickServers\GoogleWorkspace\GoogleWorkspace asUser(string $email, ?array $scopes = null)
 * @method static string|null actingAs()
 * @method static \BrickServers\GoogleWorkspace\Api\DirectoryApi directory()
 * @method static \BrickServers\GoogleWorkspace\Api\ClassroomApi classroom()
 * @method static \BrickServers\GoogleWorkspace\Api\CalendarApi calendar()
 * @method static \BrickServers\GoogleWorkspace\Api\GmailApi gmail()
 * @method static \BrickServers\GoogleWorkspace\Api\DriveApi drive()
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
