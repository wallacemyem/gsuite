<?php

namespace BrickServers\GoogleWorkspace\Tests;

use BrickServers\GoogleWorkspace\Api\CalendarApi;
use BrickServers\GoogleWorkspace\Api\ClassroomApi;
use BrickServers\GoogleWorkspace\Api\DirectoryApi;
use BrickServers\GoogleWorkspace\Api\DriveApi;
use BrickServers\GoogleWorkspace\Api\GmailApi;
use BrickServers\GoogleWorkspace\Clients\GoogleWorkspaceClient;
use BrickServers\GoogleWorkspace\Contracts\GroupsRepositoryContract;
use BrickServers\GoogleWorkspace\Contracts\UsersRepositoryContract;
use BrickServers\GoogleWorkspace\GoogleWorkspace;
use BrickServers\GoogleWorkspace\GoogleWorkspaceServiceProvider;
use BrickServers\GoogleWorkspace\Repositories\UsersRepository;
use BrickServers\GoogleWorkspace\Utilities\BatchOperations;
use Google\Service\Gmail;
use Orchestra\Testbench\TestCase;

class ConfigurationTest extends TestCase
{
    private string $credentials;

    protected function getPackageProviders($app)
    {
        return [GoogleWorkspaceServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app)
    {
        $this->credentials = tempnam(sys_get_temp_dir(), 'gw-creds');
        file_put_contents($this->credentials, json_encode([
            'type' => 'service_account',
            'client_email' => 'service-account@example.com',
            'client_id' => '1',
            'private_key' => "-----BEGIN PRIVATE KEY-----\nMIIE\n-----END PRIVATE KEY-----\n",
        ]));

        $app['config']->set('google-workspace.credentials_path', $this->credentials);
        $app['config']->set('google-workspace.subject', 'admin@example.com');
        $app['config']->set('google-workspace.retry', ['max_attempts' => 4, 'delay_ms' => 250]);
        $app['config']->set('google-workspace.timeouts', ['connect' => 3, 'read' => 20]);
    }

    protected function tearDown(): void
    {
        @unlink($this->credentials);
        parent::tearDown();
    }

    public function test_retry_and_timeout_settings_reach_the_google_client()
    {
        $client = app(GoogleWorkspaceClient::class)->getClient();

        $this->assertSame(['retries' => 3, 'initial_delay' => 0.25], $client->getConfig('retry'));
        $this->assertArrayHasKey('429', $client->getConfig('retry_map'));

        $http = $client->getHttpClient();
        $this->assertSame(3.0, $http->getConfig('connect_timeout'));
        $this->assertSame(20.0, $http->getConfig('timeout'));
        $this->assertSame('https://www.googleapis.com', rtrim((string) $http->getConfig('base_uri'), '/'));
    }

    public function test_contracts_resolve_to_the_shared_repositories()
    {
        $this->assertSame(app(UsersRepository::class), app(UsersRepositoryContract::class));
        $this->assertInstanceOf(GroupsRepositoryContract::class, app('google-workspace')->groups());
        $this->assertInstanceOf(BatchOperations::class, app('google-workspace')->batch());
        $this->assertSame(app('google-workspace'), app(GoogleWorkspace::class));
    }

    public function test_every_api_is_reachable_from_the_workspace()
    {
        $workspace = app('google-workspace');

        $this->assertInstanceOf(DirectoryApi::class, $workspace->directory());
        $this->assertInstanceOf(ClassroomApi::class, $workspace->classroom());
        $this->assertInstanceOf(CalendarApi::class, $workspace->calendar());
        $this->assertInstanceOf(GmailApi::class, $workspace->gmail());
        $this->assertInstanceOf(DriveApi::class, $workspace->drive());
        $this->assertInstanceOf(Gmail::class, $workspace->gmail()->google());
        $this->assertSame('jane@example.com', $workspace->asUser('jane@example.com')->drive()->google()->getClient()->getConfig('subject'));
    }

    public function test_facade_alias_declared_in_composer_json_exists()
    {
        $aliases = json_decode(file_get_contents(__DIR__.'/../composer.json'), true)['extra']['laravel']['aliases'];

        foreach ($aliases as $class) {
            $this->assertTrue(class_exists($class), "{$class} does not exist");
        }
    }
}
