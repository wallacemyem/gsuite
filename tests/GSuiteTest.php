<?php

namespace BrickServers\GoogleWorkspace\Tests;

use BrickServers\GoogleWorkspace\GoogleWorkspace;
use BrickServers\GoogleWorkspace\GoogleWorkspaceServiceProvider;
use BrickServers\GoogleWorkspace\Facades\GoogleWorkspaceFacade;
use BrickServers\GoogleWorkspace\DTOs\UserDTO;
use BrickServers\GoogleWorkspace\DTOs\GroupDTO;
use BrickServers\GoogleWorkspace\Exceptions\GoogleWorkspaceException;
use BrickServers\GoogleWorkspace\Repositories\UsersRepository;
use BrickServers\GoogleWorkspace\Repositories\GroupsRepository;
use BrickServers\GoogleWorkspace\Services\GoogleServicesFactory;
use Orchestra\Testbench\TestCase;

class GSuiteTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [
            GoogleWorkspaceServiceProvider::class,
        ];
    }

    protected function getApplicationAliases($app)
    {
        return [
            'GSuite' => GoogleWorkspaceFacade::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $tmpFile = sys_get_temp_dir() . '/google-workspace-test-credentials.json';

        if (!file_exists($tmpFile)) {
            file_put_contents($tmpFile, json_encode([
                'type' => 'service_account',
                'project_id' => 'test-project',
                'private_key_id' => 'dummy-key-id',
                'private_key' => "-----BEGIN PRIVATE KEY-----\nMIIEvQIBADANBgkqhkiG9w0BAQEFAASC...\n-----END PRIVATE KEY-----\n",
                'client_email' => 'service-account@example.com',
                'client_id' => '123456789012345678901',
                'auth_uri' => 'https://accounts.google.com/o/oauth2/auth',
                'token_uri' => 'https://oauth2.googleapis.com/token',
                'auth_provider_x509_cert_url' => 'https://www.googleapis.com/oauth2/v1/certs',
                'client_x509_cert_url' => 'https://www.googleapis.com/robot/v1/metadata/x509/service-account%40example.com',
            ]));
        }

        $app['config']->set('google-workspace.credentials_path', $tmpFile);
        $app['config']->set('google-workspace.subject', 'admin@example.com');
        $app['config']->set('google-workspace.domain', 'example.com');
    }

    protected function tearDown(): void
    {
        $tmpFile = sys_get_temp_dir() . '/google-workspace-test-credentials.json';

        if (file_exists($tmpFile)) {
            @unlink($tmpFile);
        }

        parent::tearDown();
    }

    public function test_the_base_class_can_be_resolved_via_google_workspace_alias()
    {
        $this->assertInstanceOf(GoogleWorkspace::class, app('google-workspace'));
        $this->assertInstanceOf(GoogleWorkspace::class, app('gsuite'), 'gsuite alias should be registered for backward compatibility');
    }

    public function test_the_base_class_can_return_users_and_groups_and_services()
    {
        $workspace = app('google-workspace');

        $this->assertInstanceOf(UsersRepository::class, $workspace->users());
        $this->assertInstanceOf(GroupsRepository::class, $workspace->groups());
        $this->assertInstanceOf(GoogleServicesFactory::class, $workspace->services());
    }

    public function test_user_dto_can_serialize_and_deserialize()
    {
        $dto = new UserDTO('john@example.com', 'John', 'Doe', 'Password1!');

        $array = $dto->toArray();
        $this->assertSame('john@example.com', $array['primaryEmail']);

        $from = UserDTO::fromArray([
            'primaryEmail' => 'jane@example.com',
            'name' => ['givenName' => 'Jane', 'familyName' => 'Doe'],
            'changePasswordAtNextLogin' => true,
            'suspended' => false,
        ]);

        $this->assertSame('jane@example.com', $from->email);
        $this->assertSame('Jane', $from->givenName);
    }

    public function test_group_dto_can_serialize_and_deserialize()
    {
        $dto = new GroupDTO('team@example.com', 'Team', 'Group testing');

        $array = $dto->toArray();
        $this->assertSame('team@example.com', $array['email']);

        $from = GroupDTO::fromArray(['email' => 'ops@example.com', 'name' => 'Ops Team', 'description' => 'Operations']);
        $this->assertSame('ops@example.com', $from->email);
        $this->assertSame('Ops Team', $from->name);
    }

    public function test_google_workspace_exception_helpers_work()
    {
        $this->assertSame(429, GoogleWorkspaceException::rateLimitExceeded()->getCode());
        $this->assertStringContainsStringIgnoringCase('invalid', GoogleWorkspaceException::invalidConfiguration('bad')->getMessage());
        $this->assertStringContainsStringIgnoringCase('credentials', GoogleWorkspaceException::missingCredentials()->getMessage());
        $this->assertStringContainsString('Resource not found', GoogleWorkspaceException::resourceNotFound('User', 'x')->getMessage());
    }

    public function test_users_repository_works_with_fake_directory_services()
    {
        $usersResource = new class {
            public function insert($user)
            {
                return (object)[
                    'primaryEmail' => $user->primaryEmail,
                    'name' => ['givenName' => $user->name->givenName, 'familyName' => $user->name->familyName],
                    'changePasswordAtNextLogin' => $user->changePasswordAtNextLogin,
                    'suspended' => $user->suspended,
                    'phones' => [],
                    'organizations' => [],
                ];
            }

            public function get($userKey, $options)
            {
                if ($userKey !== 'john@example.com') {
                    throw new \Exception('not found');
                }

                return (object)[
                    'primaryEmail' => 'john@example.com',
                    'name' => ['givenName' => 'John', 'familyName' => 'Doe'],
                    'changePasswordAtNextLogin' => false,
                    'suspended' => false,
                    'phones' => [],
                    'organizations' => [],
                ];
            }

            public function listUsers($options)
            {
                return new class {
                    public function getUsers()
                    {
                        return [(object)[
                            'primaryEmail' => 'user1@example.com',
                            'name' => ['givenName' => 'User1', 'familyName' => 'Example'],
                            'changePasswordAtNextLogin' => false,
                            'suspended' => false,
                            'phones' => [],
                            'organizations' => [],
                        ]];
                    }

                    public function getNextPageToken()
                    {
                        return null;
                    }
                };
            }

            public function update($userKey, $user)
            {
                return (object)[
                    'primaryEmail' => $userKey,
                    'name' => ['givenName' => 'Updated', 'familyName' => 'Name'],
                    'changePasswordAtNextLogin' => false,
                    'suspended' => false,
                    'phones' => [],
                    'organizations' => [],
                ];
            }

            public function delete($userKey)
            {
                return null;
            }

            public function makeAdmin($userKey, $makeAdminRequest)
            {
                if ($userKey !== 'john@example.com') {
                    throw new \Exception('not found');
                }

                return null;
            }
        };

        $aliasResource = new class {
            public function insert($userKey, $alias)
            {
                return null;
            }

            public function delete($userKey, $alias)
            {
                return null;
            }
        };

        $directory = new class(new \Google\Client(), $usersResource, $aliasResource) extends \Google\Service\Directory {
            public $users;
            public $users_aliases;

            public function __construct($client, $users, $aliases)
            {
                parent::__construct($client);
                $this->users = $users;
                $this->users_aliases = $aliases;
            }
        };

        $services = $this->createMock(GoogleServicesFactory::class);
        $services->method('directory')->willReturn($directory);

        $repo = new UsersRepository($services, 'example.com', ['root@example.com']);

        $created = $repo->create(new UserDTO('john@example.com', 'John', 'Doe', 'Password123'));
        $this->assertSame('john@example.com', $created->email);

        $fetched = $repo->get('john@example.com');
        $this->assertSame('john@example.com', $fetched->email);

        $listed = $repo->list(10);
        $this->assertCount(1, $listed['users']);

        $updated = $repo->update('john@example.com', new UserDTO('john@example.com', 'Updated', 'Name'));
        $this->assertSame('Updated', $updated->givenName);

        $this->assertTrue($repo->delete('john@example.com'));
        $this->assertTrue($repo->addAlias('john@example.com', 'alias@example.com'));
        $this->assertTrue($repo->removeAlias('john@example.com', 'alias@example.com'));
        $this->assertTrue($repo->makeAdmin('john@example.com'));

        $this->expectException(GoogleWorkspaceException::class);
        $repo->delete('root@example.com');
    }

    public function test_groups_repository_works_with_fake_directory_services()
    {
        $groupResource = new class {
            public function insert($group)
            {
                return (object)['email' => $group->email, 'name' => $group->name, 'description' => $group->description];
            }

            public function get($groupKey)
            {
                if ($groupKey !== 'team@example.com') {
                    throw new \Exception('not found');
                }

                return (object)['email' => 'team@example.com', 'name' => 'Team', 'description' => 'Group'];
            }

            public function listGroups($options)
            {
                return new class {
                    public function getGroups()
                    {
                        return [(object)['email' => 'team@example.com', 'name' => 'Team', 'description' => 'Group']];
                    }
                    public function getNextPageToken()
                    {
                        return null;
                    }
                };
            }

            public function update($groupKey, $group)
            {
                return (object)['email' => $groupKey, 'name' => 'Updated', 'description' => 'Updated description'];
            }

            public function delete($groupKey)
            {
                return null;
            }
        };

        $membersResource = new class {
            public function insert($groupKey, $member)
            {
                return null;
            }

            public function delete($groupKey, $userEmail)
            {
                return null;
            }
        };

        $directory = new class(new \Google\Client(), $groupResource, $membersResource) extends \Google\Service\Directory {
            public $groups;
            public $members;

            public function __construct($client, $groups, $members)
            {
                parent::__construct($client);
                $this->groups = $groups;
                $this->members = $members;
            }
        };

        $services = $this->createMock(GoogleServicesFactory::class);
        $services->method('directory')->willReturn($directory);

        $repo = new GroupsRepository($services, 'example.com', null, ['rootgroup@example.com']);

        $created = $repo->create(new GroupDTO('team@example.com', 'Team', 'Group'));
        $this->assertSame('team@example.com', $created->email);

        $fetched = $repo->get('team@example.com');
        $this->assertSame('team@example.com', $fetched->email);

        $listed = $repo->list(10);
        $this->assertCount(1, $listed['groups']);

        $updated = $repo->update('team@example.com', new GroupDTO('team@example.com', 'Updated', 'Updated desc'));
        $this->assertSame('Updated', $updated->name);

        $this->assertTrue($repo->delete('team@example.com'));
        $this->assertTrue($repo->addMember('team@example.com', 'user@example.com'));
        $this->assertTrue($repo->removeMember('team@example.com', 'user@example.com'));

        $this->expectException(GoogleWorkspaceException::class);
        $repo->delete('rootgroup@example.com');
    }
}
