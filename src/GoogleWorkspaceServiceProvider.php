<?php

namespace BrickServers\GoogleWorkspace;

use Illuminate\Support\ServiceProvider;
use BrickServers\GoogleWorkspace\Clients\GoogleWorkspaceClient;
use BrickServers\GoogleWorkspace\Services\GoogleServicesFactory;
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

        // Register Main Facade
        $this->app->singleton('google-workspace', function () {
            return new GoogleWorkspace(
                services: app(GoogleServicesFactory::class),
                domain: config('google-workspace.domain'),
                undeletableUsers: config('google-workspace.undeletable.users', []),
                undeletableGroups: config('google-workspace.undeletable.groups', []),
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
