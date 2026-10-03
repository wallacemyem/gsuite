<?php

namespace BrickServers\GoogleWorkspace;

use BrickServers\GoogleWorkspace\Clients\GoogleWorkspaceClient;
use BrickServers\GoogleWorkspace\Contracts\GroupsRepositoryContract;
use BrickServers\GoogleWorkspace\Contracts\UsersRepositoryContract;
use BrickServers\GoogleWorkspace\Repositories\GroupsRepository;
use BrickServers\GoogleWorkspace\Repositories\UsersRepository;
use BrickServers\GoogleWorkspace\Services\GoogleServicesFactory;
use BrickServers\GoogleWorkspace\Support\ProtectedResources;
use BrickServers\GoogleWorkspace\Utilities\BatchOperations;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Google Workspace Service Provider
 */
class GoogleWorkspaceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/google-workspace.php' => config_path('google-workspace.php'),
        ], 'config');
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/google-workspace.php', 'google-workspace');

        // Register Google Client
        $this->app->singleton(GoogleWorkspaceClient::class, function () {
            return GoogleWorkspaceClient::make(
                credentialsPath: (string) config('google-workspace.credentials_path'),
                subject: (string) config('google-workspace.subject'),
                scopes: config('google-workspace.scopes', []),
                logger: $this->logger(),
                retry: config('google-workspace.retry', []),
                timeouts: config('google-workspace.timeouts', []),
            );
        });

        // Register Services Factory
        $this->app->singleton(GoogleServicesFactory::class, function () {
            return new GoogleServicesFactory(
                app(GoogleWorkspaceClient::class),
                $this->logger(),
            );
        });

        // Safety rules shared by the repositories and the API wrappers
        $this->app->singleton(ProtectedResources::class, function () {
            return new ProtectedResources(
                services: app(GoogleServicesFactory::class),
                users: $this->protectedList('users'),
                groups: $this->protectedList('groups'),
                allowAdminPromotion: filter_var(config('google-workspace.allow_admin_promotion', false), FILTER_VALIDATE_BOOL),
            );
        });

        // Register Users Repository
        $this->app->singleton(UsersRepository::class, function () {
            return new UsersRepository(
                services: app(GoogleServicesFactory::class),
                domain: (string) config('google-workspace.domain'),
                logger: $this->logger(),
                protection: app(ProtectedResources::class),
            );
        });
        $this->app->alias(UsersRepository::class, UsersRepositoryContract::class);

        // Register Groups Repository
        $this->app->singleton(GroupsRepository::class, function () {
            return new GroupsRepository(
                services: app(GoogleServicesFactory::class),
                domain: (string) config('google-workspace.domain'),
                logger: $this->logger(),
                protection: app(ProtectedResources::class),
            );
        });
        $this->app->alias(GroupsRepository::class, GroupsRepositoryContract::class);

        $this->app->singleton(BatchOperations::class, function () {
            return new BatchOperations(
                app(UsersRepositoryContract::class),
                app(GroupsRepositoryContract::class),
                app(GoogleServicesFactory::class),
                $this->logger(),
            );
        });

        // Register Main Facade
        $this->app->singleton('google-workspace', function () {
            return new GoogleWorkspace(
                services: app(GoogleServicesFactory::class),
                users: app(UsersRepositoryContract::class),
                groups: app(GroupsRepositoryContract::class),
                logger: $this->logger(),
                protection: app(ProtectedResources::class),
            );
        });

        $this->app->alias('google-workspace', GoogleWorkspace::class);

        // Backward compatibility alias for old packages
        $this->app->alias('google-workspace', 'gsuite');
    }

    private function logger(): LoggerInterface
    {
        if (! filter_var(config('google-workspace.logging.enabled', true), FILTER_VALIDATE_BOOL)) {
            return new NullLogger;
        }

        $channel = config('google-workspace.logging.channel');

        return $channel ? app('log')->channel($channel) : app('log');
    }

    private function protectedList(string $type): array
    {
        $list = config("google-workspace.undeletable.{$type}", []);
        if (is_string($list)) {
            $list = explode(',', $list);
        }

        return array_values(array_filter(array_map('trim', (array) $list)));
    }

    public function provides(): array
    {
        return [
            GoogleWorkspaceClient::class,
            GoogleServicesFactory::class,
            UsersRepository::class,
            UsersRepositoryContract::class,
            GroupsRepository::class,
            GroupsRepositoryContract::class,
            BatchOperations::class,
            ProtectedResources::class,
            'google-workspace',
            GoogleWorkspace::class,
            'gsuite',
        ];
    }
}
