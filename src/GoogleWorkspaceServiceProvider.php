<?php

namespace BrickServers\GoogleWorkspace;

use Illuminate\Support\ServiceProvider;
use BrickServers\GoogleWorkspace\Clients\GoogleWorkspaceClient;
use BrickServers\GoogleWorkspace\Services\GoogleServicesFactory;
use BrickServers\GoogleWorkspace\Repositories\UsersRepository;
use BrickServers\GoogleWorkspace\Repositories\GroupsRepository;
use BrickServers\GoogleWorkspace\Enums\ApiScope;

/**
 * Google Workspace Service Provider
 */
class GoogleWorkspaceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/google-workspace.php' => config_path('google-workspace.php'),
        ], 'config');
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/google-workspace.php', 'google-workspace');

        // Register Google Client
        $this->app->singleton(GoogleWorkspaceClient::class, function () {
            return GoogleWorkspaceClient::make(
                credentialsPath: config('google-workspace.credentials_path'),
                subject: config('google-workspace.subject'),
                scopes: config('google-workspace.scopes', []),
                logger: app('log'),
            );
        });

        // Register Services Factory
        $this->app->singleton(GoogleServicesFactory::class, function () {
            return new GoogleServicesFactory(
                app(GoogleWorkspaceClient::class),
                app('log'),
            );
        });

        // Register Users Repository
        $this->app->singleton(UsersRepository::class, function () {
            return new UsersRepository(
                services: app(GoogleServicesFactory::class),
                domain: config('google-workspace.domain'),
                undeletableUsers: config('google-workspace.undeletable.users', []),
            );
        });

        // Register Groups Repository
        $this->app->singleton(GroupsRepository::class, function () {
            return new GroupsRepository(
                services: app(GoogleServicesFactory::class),
                domain: config('google-workspace.domain'),
                logger: app('log'),
                undeletableGroups: config('google-workspace.undeletable.groups', []),
            );
        });

        // Register Main Facade
        $this->app->singleton('google-workspace', function () {
            return new GoogleWorkspace(
                services: app(GoogleServicesFactory::class),
                users: app(UsersRepository::class),
                groups: app(GroupsRepository::class),
            );
        });
    }

    public function provides(): array
    {
        return [
            GoogleWorkspaceClient::class,
            GoogleServicesFactory::class,
            'google-workspace',
        ];
    }
}
