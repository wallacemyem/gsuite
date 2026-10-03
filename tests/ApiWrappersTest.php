<?php

namespace BrickServers\GoogleWorkspace\Tests;

use BrickServers\GoogleWorkspace\Api\ApiService;
use BrickServers\GoogleWorkspace\Clients\GoogleWorkspaceClient;
use BrickServers\GoogleWorkspace\Exceptions\GoogleWorkspaceException;
use BrickServers\GoogleWorkspace\GoogleWorkspace;
use BrickServers\GoogleWorkspace\Repositories\GroupsRepository;
use BrickServers\GoogleWorkspace\Repositories\UsersRepository;
use BrickServers\GoogleWorkspace\Services\GoogleServicesFactory;
use BrickServers\GoogleWorkspace\Support\ProtectedResources;
use Google\Client;
use Google\Service\Calendar;
use Google\Service\Classroom;
use Google\Service\Directory;
use Google\Service\Directory\Role;
use Google\Service\Directory\RoleAssignment;
use Google\Service\Directory\User;
use Google\Service\Directory\UserMakeAdmin;
use Google\Service\Drive;
use Google\Service\Exception;
use Google\Service\Gmail;
use Google\Service\Resource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use ReflectionClass;
use ReflectionMethod;
use ReflectionObject;
use ReflectionProperty;

class ApiWrappersTest extends TestCase
{
    private const SERVICES = [
        'directory' => [Directory::class, 'directory'],
        'classroom' => [Classroom::class, 'classroom'],
        'calendar' => [Calendar::class, 'calendar'],
        'gmail' => [Gmail::class, 'gmail'],
        'drive' => [Drive::class, 'drive'],
    ];

    private array $calls = [];

    private object $logger;

    // --- coverage -------------------------------------------------------------

    public static function services(): array
    {
        return array_map(fn ($s) => [$s[0], $s[1]], self::SERVICES);
    }

    /**
     * Every method of every resource Google's client exposes has a wrapper with the
     * same parameters, reachable from the workspace.
     */
    #[DataProvider('services')]
    public function test_every_google_method_has_a_wrapper(string $serviceClass, string $accessor)
    {
        $manifest = require __DIR__.'/../src/Api/manifest.php';
        $workspace = $this->workspace(new $serviceClass(new Client));
        $api = $workspace->{$accessor}();
        $google = new $serviceClass(new Client);
        $missing = [];
        $checked = 0;

        foreach ((new ReflectionObject($google))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $resource = $property->getValue($google);
            if (! $resource instanceof \Google\Service\Resource) {
                continue;
            }

            $name = $property->getName();
            foreach ((new ReflectionClass($resource))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->getDeclaringClass()->getName() !== get_class($resource)) {
                    continue;
                }

                $label = "{$name}.{$method->getName()}";
                if (! in_array($method->getName(), $manifest['services'][$accessor][$name] ?? [], true)) {
                    $missing[] = $label;

                    continue;
                }

                $wrapper = $this->wrapperFor($api, $name);
                $this->assertTrue(method_exists($wrapper, $method->getName()), "{$label} has no wrapper method");
                $this->assertSame(
                    array_map(fn ($p) => $p->getName(), $method->getParameters()),
                    array_map(fn ($p) => $p->getName(), (new ReflectionMethod($wrapper, $method->getName()))->getParameters()),
                    "{$label} parameters differ",
                );
                $checked++;
            }
        }

        $this->assertGreaterThan(0, $checked);

        if ($missing) {
            // Newer google/apiclient-services than the wrappers were generated from
            $this->markTestIncomplete(sprintf(
                'google/apiclient-services has %d method(s) newer than the generated wrappers (%s): %s. Run composer generate-api.',
                count($missing), $manifest['version'], implode(', ', $missing),
            ));
        }
    }

    public function test_the_manifest_matches_the_generated_classes()
    {
        $manifest = require __DIR__.'/../src/Api/manifest.php';
        $total = 0;

        foreach ($manifest['services'] as $accessor => $resources) {
            $api = $this->workspace(new (self::SERVICES[$accessor][0])(new Client))->{$accessor}();
            foreach ($resources as $property => $methods) {
                $wrapper = $this->wrapperFor($api, $property);
                foreach ($methods as $method) {
                    $this->assertTrue(method_exists($wrapper, $method), "{$accessor}.{$property}.{$method}");
                    $total++;
                }
            }
        }

        $this->assertGreaterThan(400, $total);
    }

    // --- behaviour ------------------------------------------------------------

    public function test_calls_are_forwarded_and_return_googles_response()
    {
        $user = $this->workspace()->directory()->users()->get('jane@example.com', ['projection' => 'full']);

        $this->assertSame('jane@example.com', $user->primaryEmail);
        $this->assertSame([['users.get', ['jane@example.com', ['projection' => 'full']]]], $this->calls);
    }

    public function test_google_errors_become_workspace_exceptions()
    {
        try {
            $this->workspace()->directory()->users()->get('missing@example.com');
            $this->fail('Expected an exception');
        } catch (GoogleWorkspaceException $e) {
            $this->assertSame(5, $e->getCode());
            $this->assertInstanceOf(Exception::class, $e->getPrevious());
        }
    }

    public function test_only_changes_are_audit_logged()
    {
        $directory = $this->workspace()->directory();
        $directory->users()->get('jane@example.com');
        $directory->users()->signOut('jane@example.com');

        $this->assertSame([['info', 'Google Workspace API change', ['api' => 'directory.users.signOut', 'keys' => ['jane@example.com']]]], $this->logger->records);
    }

    public function test_protected_users_cannot_be_deleted_or_suspended_through_the_raw_api()
    {
        $users = $this->workspace(protectedUsers: ['root@example.com'])->directory()->users();

        foreach ([
            fn () => $users->delete('1001'),
            fn () => $users->patch('ROOT@example.com', new User(['suspended' => true])),
            fn () => $users->update('root@example.com', new User(['primaryEmail' => 'renamed@example.com'])),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('Expected the protected user to be refused');
            } catch (GoogleWorkspaceException $e) {
                $this->assertSame(6, $e->getCode());
            }
        }

        $this->assertSame([], array_filter($this->calls, fn ($c) => $c[0] !== 'users.get'), 'Nothing should reach Google');

        // Unprotected users and harmless changes still work
        $users->delete('jane@example.com');
        $users->patch('root@example.com', new User(['orgUnitPath' => '/IT']));
        $this->assertCount(2, array_filter($this->calls, fn ($c) => $c[0] !== 'users.get'));
    }

    public function test_super_admin_cannot_be_granted_through_the_raw_api_unless_allowed()
    {
        $directory = $this->workspace()->directory();

        foreach ([
            fn () => $directory->users()->makeAdmin('jane@example.com', new UserMakeAdmin(['status' => true])),
            fn () => $directory->roleAssignments()->insert('my_customer', new RoleAssignment(['roleId' => '1', 'assignedTo' => '2002'])),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('Expected admin promotion to be refused');
            } catch (GoogleWorkspaceException $e) {
                $this->assertSame(403, $e->getCode());
            }
        }

        // Revoking admin and assigning ordinary roles are fine
        $directory->users()->makeAdmin('jane@example.com', new UserMakeAdmin(['status' => false]));
        $directory->roleAssignments()->insert('my_customer', new RoleAssignment(['roleId' => '2', 'assignedTo' => '2002']));

        $allowed = $this->workspace(allowAdminPromotion: true)->directory();
        $allowed->users()->makeAdmin('jane@example.com', new UserMakeAdmin(['status' => true]));

        $this->assertSame(
            ['users.makeAdmin', 'roleAssignments.insert', 'users.makeAdmin'],
            array_column(array_filter($this->calls, fn ($c) => $c[0] !== 'roles.get'), 0),
        );
    }

    public function test_paginate_follows_page_tokens()
    {
        $emails = array_map(
            fn ($u) => $u->primaryEmail,
            iterator_to_array($this->workspace()->directory()->users()->paginate('listUsers', ['customer' => 'my_customer']), false),
        );

        $this->assertSame(['page1@example.com', 'page2@example.com'], $emails);
        $this->assertSame([
            ['users.listUsers', [['customer' => 'my_customer']]],
            ['users.listUsers', [['customer' => 'my_customer', 'pageToken' => 'next']]],
        ], $this->calls);
    }

    public function test_paginate_rejects_non_list_methods()
    {
        $this->expectExceptionCode(8);
        iterator_to_array($this->workspace()->directory()->users()->paginate('delete', 'x'));
    }

    public function test_as_user_impersonates_for_api_calls_but_not_admin_repositories()
    {
        $credentials = tempnam(sys_get_temp_dir(), 'gw');
        file_put_contents($credentials, json_encode([
            'type' => 'service_account', 'client_email' => 'sa@example.com', 'client_id' => '1',
            'private_key' => "-----BEGIN PRIVATE KEY-----\nMIIE\n-----END PRIVATE KEY-----\n",
        ]));

        try {
            $services = new GoogleServicesFactory(GoogleWorkspaceClient::make($credentials, 'admin@example.com', ['scope-a']));
            $workspace = new GoogleWorkspace($services, new UsersRepository($services, 'example.com'), new GroupsRepository($services, 'example.com'));

            $jane = $workspace->asUser('jane@example.com');
            $janeWithScopes = $workspace->asUser('jane@example.com', ['scope-b']);

            $this->assertSame('jane@example.com', $jane->actingAs());
            $this->assertSame('jane@example.com', $jane->services()->client()->getSubject());
            $this->assertSame('jane@example.com', $jane->gmail()->google()->getClient()->getConfig('subject'));
            $this->assertSame(['scope-a'], $jane->services()->client()->getClient()->getScopes());
            $this->assertSame(['scope-b'], $janeWithScopes->services()->client()->getClient()->getScopes());
            $this->assertSame($jane->services(), $workspace->asUser('JANE@example.com')->services(), 'Impersonated clients are cached');

            $this->assertSame('admin@example.com', $workspace->services()->client()->getSubject());
            $this->assertSame($workspace->users(), $jane->users());
        } finally {
            unlink($credentials);
        }
    }

    // --- helpers --------------------------------------------------------------

    private function wrapperFor(ApiService $api, string $property): object
    {
        $accessor = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $property))));
        $this->assertTrue(method_exists($api, $accessor), get_class($api)."::{$accessor}() is missing");

        return $api->{$accessor}();
    }

    private function workspace(
        ?object $service = null,
        array $protectedUsers = [],
        bool $allowAdminPromotion = false,
    ): GoogleWorkspace {
        $service ??= $this->fakeDirectory();

        $services = $this->createStub(GoogleServicesFactory::class);
        foreach (self::SERVICES as $name => [$class]) {
            if ($service instanceof $class) {
                $services->method($name)->willReturn($service);
            }
        }

        $this->logger = new class extends AbstractLogger
        {
            public array $records = [];

            public function log($level, $message, array $context = []): void
            {
                $this->records[] = [$level, (string) $message, $context];
            }
        };

        return new GoogleWorkspace(
            $services,
            new UsersRepository($services, 'example.com'),
            new GroupsRepository($services, 'example.com'),
            $this->logger,
            new ProtectedResources($services, $protectedUsers, [], $allowAdminPromotion),
        );
    }

    private function fakeDirectory(): Directory
    {
        $record = function (string $call, array $args) {
            $this->calls[] = [$call, $args];
        };

        $directory = new Directory(new Client);

        $directory->users = new class($record)
        {
            public function __construct(private \Closure $record) {}

            public function get($userKey, $optParams = [])
            {
                ($this->record)('users.get', func_get_args());
                if ($userKey === 'missing@example.com') {
                    throw new Exception('Resource Not Found: userKey', 404);
                }
                if (in_array(strtolower($userKey), ['root@example.com', '1001'], true)) {
                    return new User(['primaryEmail' => 'root@example.com', 'id' => '1001']);
                }

                return new User(['primaryEmail' => $userKey, 'id' => '2002']);
            }

            public function listUsers($optParams = [])
            {
                ($this->record)('users.listUsers', func_get_args());
                $page = isset($optParams['pageToken']) ? 2 : 1;

                return new Directory\Users([
                    'users' => [['primaryEmail' => "page{$page}@example.com"]],
                    'nextPageToken' => $page === 1 ? 'next' : null,
                ]);
            }

            public function __call($method, $args)
            {
                ($this->record)("users.{$method}", $args);
            }
        };

        $directory->roles = new class($record)
        {
            public function __construct(private \Closure $record) {}

            public function get($customer, $roleId, $optParams = [])
            {
                ($this->record)('roles.get', func_get_args());

                return new Role(['roleId' => $roleId, 'isSuperAdminRole' => $roleId === '1']);
            }
        };

        $directory->roleAssignments = new class($record)
        {
            public function __construct(private \Closure $record) {}

            public function insert($customer, $assignment, $optParams = [])
            {
                ($this->record)('roleAssignments.insert', func_get_args());

                return $assignment;
            }
        };

        return $directory;
    }
}
