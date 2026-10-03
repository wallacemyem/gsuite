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
use BrickServers\GoogleWorkspace\Facades\GoogleWorkspaceFacade;
use BrickServers\GoogleWorkspace\GoogleWorkspace;
use BrickServers\GoogleWorkspace\GoogleWorkspaceServiceProvider;
use BrickServers\GoogleWorkspace\Repositories\UsersRepository;
use BrickServers\GoogleWorkspace\Utilities\BatchOperations;
use Google\Service\Gmail;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

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

    public function test_network_failures_are_retried_up_to_max_attempts()
    {
        config()->set('google-workspace.retry', ['max_attempts' => 3, 'delay_ms' => 0]);
        $request = new Request('GET', 'https://admin.googleapis.com/admin/directory/v1/users');
        $connectError = fn () => new ConnectException('Could not resolve host', $request);
        $timeout = fn () => new RequestException('Operation timed out', $request);

        // Two transport failures, then success: retried transparently
        $mock = new MockHandler([$connectError(), $timeout(), new Response(200)]);
        $http = $this->httpClientWith($mock);
        $this->assertSame(200, $http->send($request)->getStatusCode());
        $this->assertSame(0, $mock->count());

        // Still failing after max_attempts: the error surfaces
        $mock = new MockHandler([$connectError(), $connectError(), $connectError(), new Response(200)]);
        try {
            $this->httpClientWith($mock)->send($request);
            $this->fail('Expected the network error after 3 attempts');
        } catch (ConnectException) {
            $this->assertSame(1, $mock->count(), 'Exactly 3 attempts should be made');
        }

        // Error responses are left to Google's retry runner, so they are not retried twice
        $mock = new MockHandler([new Response(503), new Response(200)]);
        $this->assertSame(503, $this->httpClientWith($mock)->send($request)->getStatusCode());
        $this->assertSame(1, $mock->count());
    }

    public static function ambiguousFailures(): array
    {
        // The request may have reached Google: read timeout, dropped connection, empty reply
        return [
            'read timeout' => ['cURL error 28: Operation timed out after 60000 milliseconds with 0 bytes received'],
            'empty reply' => ['cURL error 52: Empty reply from server'],
            'connection reset' => ['cURL error 56: Recv failure: Connection reset by peer'],
            'no error code' => ['Connection closed unexpectedly'],
        ];
    }

    #[DataProvider('ambiguousFailures')]
    public function test_non_idempotent_requests_are_not_resent_after_ambiguous_failures(string $error)
    {
        config()->set('google-workspace.retry', ['max_attempts' => 3, 'delay_ms' => 0]);
        $insert = new Request('POST', 'https://www.googleapis.com/calendar/v3/calendars/primary/events');

        foreach ([ConnectException::class, RequestException::class] as $class) {
            $mock = new MockHandler([new $class($error, $insert), new Response(200)]);
            try {
                $this->httpClientWith($mock)->send($insert);
                $this->fail("{$class} on a POST must not be retried: it may already have created the event");
            } catch (TransferException) {
                $this->assertSame(1, $mock->count(), "{$class} on a POST must not be retried");
            }
        }

        // The same failure on an idempotent request is retried
        $get = new Request('GET', 'https://www.googleapis.com/calendar/v3/calendars/primary/events');
        $mock = new MockHandler([new ConnectException($error, $get), new Response(200)]);
        $this->assertSame(200, $this->httpClientWith($mock)->send($get)->getStatusCode());
    }

    public static function failuresBeforeSending(): array
    {
        return [
            'dns' => ['cURL error 6: Could not resolve host: www.googleapis.com'],
            'connection refused' => ['cURL error 7: Failed to connect to www.googleapis.com port 443'],
            'tls handshake' => ['cURL error 35: OpenSSL SSL_connect: Connection reset by peer'],
        ];
    }

    #[DataProvider('failuresBeforeSending')]
    public function test_non_idempotent_requests_are_retried_when_the_connection_never_opened(string $error)
    {
        config()->set('google-workspace.retry', ['max_attempts' => 3, 'delay_ms' => 0]);
        $insert = new Request('POST', 'https://www.googleapis.com/calendar/v3/calendars/primary/events');

        $mock = new MockHandler([new ConnectException($error, $insert), new Response(200)]);
        $this->assertSame(200, $this->httpClientWith($mock)->send($insert)->getStatusCode());
    }

    private function httpClientWith(MockHandler $mock): ClientInterface
    {
        $this->app->forgetInstance(GoogleWorkspaceClient::class);
        $http = app(GoogleWorkspaceClient::class)->getClient()->getHttpClient();
        $http->getConfig('handler')->setHandler($mock);

        return $http;
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

    public function test_facade_proxies_the_workspace()
    {
        $this->assertSame(app(UsersRepositoryContract::class), GoogleWorkspaceFacade::users());
        $this->assertSame('jane@example.com', GoogleWorkspaceFacade::asUser('jane@example.com')->actingAs());
    }

    public function test_facade_alias_declared_in_composer_json_exists()
    {
        $aliases = json_decode(file_get_contents(__DIR__.'/../composer.json'), true)['extra']['laravel']['aliases'];

        foreach ($aliases as $class) {
            $this->assertTrue(class_exists($class), "{$class} does not exist");
        }
    }
}
