<?php

namespace BrickServers\GoogleWorkspace\Tests;

use BrickServers\GoogleWorkspace\DTOs\GroupDTO;
use BrickServers\GoogleWorkspace\DTOs\UserDTO;
use BrickServers\GoogleWorkspace\Exceptions\GoogleWorkspaceException;
use BrickServers\GoogleWorkspace\Repositories\GroupsRepository;
use BrickServers\GoogleWorkspace\Repositories\UsersRepository;
use BrickServers\GoogleWorkspace\Services\GoogleServicesFactory;
use Google\Service\Directory;
use Google\Service\Directory\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

class RepositoryBehaviourTest extends TestCase
{
    /** @var array<int, array{0: string, 1: string, 2: mixed}> */
    private array $calls = [];

    private function services(array $resources): GoogleServicesFactory
    {
        $directory = new Directory(new \Google\Client());
        foreach ($resources as $name => $resource) {
            $directory->{$name} = $resource;
        }

        $services = $this->createMock(GoogleServicesFactory::class);
        $services->method('directory')->willReturn($directory);

        return $services;
    }

    /**
     * A fake users resource that records calls. root@example.com (ID 1001, alias
     * admin-alias@example.com) exists; anything listed in $missing returns a 404.
     */
    private function usersResource(array $missing = [], ?\Throwable $failWith = null): object
    {
        return new class($this->calls, $missing, $failWith) {
            public function __construct(private array &$calls, private array $missing, private ?\Throwable $failWith) {}

            public function get($userKey, $options = [])
            {
                $this->calls[] = ['get', $userKey, $options];
                if ($this->failWith) {
                    throw $this->failWith;
                }
                if (in_array($userKey, $this->missing, true)) {
                    throw new \Google\Service\Exception('Resource Not Found: userKey', 404);
                }
                if (in_array(strtolower($userKey), ['root@example.com', '1001', 'admin-alias@example.com'], true)) {
                    return new User(['primaryEmail' => 'root@example.com', 'id' => '1001', 'aliases' => ['admin-alias@example.com']]);
                }

                return new User(['primaryEmail' => $userKey, 'id' => '2002', 'name' => ['givenName' => 'A', 'familyName' => 'B']]);
            }

            public function insert($user)
            {
                $this->calls[] = ['insert', null, $user->toSimpleObject()];
                return $user;
            }

            public function update($userKey, $user)
            {
                $this->calls[] = ['update', $userKey, $user->toSimpleObject()];
                return new User(['primaryEmail' => $user->primaryEmail ?? $userKey, 'suspended' => $user->suspended]);
            }

            public function delete($userKey)
            {
                $this->calls[] = ['delete', $userKey, null];
            }

            public function makeAdmin($userKey, $request)
            {
                $this->calls[] = ['makeAdmin', $userKey, null];
            }

            public function listUsers($options)
            {
                $this->calls[] = ['list', null, $options];
                $page = ($options['pageToken'] ?? null) === 'page-2' ? 2 : 1;

                return new Directory\Users([
                    'users' => [['primaryEmail' => "user{$page}@example.com"]],
                    'nextPageToken' => $page === 1 ? 'page-2' : null,
                ]);
            }
        };
    }

    private function usersRepo(object $resource, array $protected = ['root@example.com'], ...$options): UsersRepository
    {
        return new UsersRepository($this->services(['users' => $resource]), 'example.com', $protected, ...$options);
    }

    private function callsOf(string $method): array
    {
        return array_values(array_filter($this->calls, fn ($call) => $call[0] === $method));
    }

    // --- 1. updates only send the fields that were set ---

    public function test_update_only_sends_fields_that_were_set()
    {
        $this->usersRepo($this->usersResource())->update('john@example.com', new UserDTO('john@example.com', givenName: 'Johnny'));

        $payload = $this->callsOf('update')[0][2];
        $this->assertEquals((object)['primaryEmail' => 'john@example.com', 'name' => (object)['givenName' => 'Johnny']], $payload);
        $this->assertObjectNotHasProperty('changePasswordAtNextLogin', $payload, 'Updating a name must not force a password reset');
        $this->assertObjectNotHasProperty('suspended', $payload);
    }

    public function test_update_can_set_boolean_fields_to_false()
    {
        $this->usersRepo($this->usersResource())->update('john@example.com', new UserDTO('', changePasswordAtNextLogin: false, suspended: false));

        $this->assertEquals((object)['changePasswordAtNextLogin' => false, 'suspended' => false], $this->callsOf('update')[0][2]);
    }

    public function test_update_with_no_fields_is_rejected()
    {
        $this->expectExceptionCode(8);
        $this->usersRepo($this->usersResource())->update('john@example.com', new UserDTO(''));
    }

    public function test_phone_and_title_are_sent()
    {
        $this->usersRepo($this->usersResource())->update('john@example.com', new UserDTO('', phone: '+15551234', title: 'Engineer'));

        $payload = $this->callsOf('update')[0][2];
        $this->assertSame('+15551234', $payload->phones[0]['value']);
        $this->assertSame('Engineer', $payload->organizations[0]['title']);
    }

    public function test_create_forces_password_change_by_default_and_requires_names()
    {
        $repo = $this->usersRepo($this->usersResource());

        $repo->create(new UserDTO('new@example.com', 'New', 'User', 'Password123'));
        $this->assertTrue($this->callsOf('insert')[0][2]->changePasswordAtNextLogin);

        $repo->create(new UserDTO('new2@example.com', 'New', 'User', changePasswordAtNextLogin: false));
        $this->assertFalse($this->callsOf('insert')[1][2]->changePasswordAtNextLogin);

        $this->expectExceptionCode(4);
        $repo->create(new UserDTO('new3@example.com', 'OnlyGiven'));
    }

    // --- 3. errors keep their real meaning ---

    public static function googleErrors(): array
    {
        return [
            'not found' => [new \Google\Service\Exception('nope', 404), 5],
            'forbidden' => [new \Google\Service\Exception('Not Authorized', 403), 403],
            'unauthenticated' => [new \Google\Service\Exception('Login Required', 401), 403],
            'rate limited (429)' => [new \Google\Service\Exception('slow down', 429), 429],
            'rate limited (403 reason)' => [new \Google\Service\Exception('slow down', 403, null, [['reason' => 'userRateLimitExceeded']]), 429],
            'server error' => [new \Google\Service\Exception('oops', 500), 3],
            'network' => [new \GuzzleHttp\Exception\ConnectException('timed out', new \GuzzleHttp\Psr7\Request('GET', '/')), 7],
        ];
    }

    #[DataProvider('googleErrors')]
    public function test_google_errors_map_to_matching_exception_codes(\Throwable $error, int $code)
    {
        $repo = $this->usersRepo($this->usersResource(failWith: $error));

        try {
            $repo->get('john@example.com');
            $this->fail('Expected an exception');
        } catch (GoogleWorkspaceException $e) {
            $this->assertSame($code, $e->getCode());
            $this->assertSame($error, $e->getPrevious());
        }
    }

    public function test_deleting_a_missing_user_reports_not_found()
    {
        $this->expectExceptionCode(5);
        $this->usersRepo($this->usersResource(missing: ['ghost@example.com']))->delete('ghost@example.com');
    }

    // --- 5. safer defaults ---

    public function test_make_admin_is_disabled_by_default()
    {
        try {
            $this->usersRepo($this->usersResource())->makeAdmin('john@example.com');
            $this->fail('Expected an exception');
        } catch (GoogleWorkspaceException $e) {
            $this->assertSame(403, $e->getCode());
        }
        $this->assertSame([], $this->callsOf('makeAdmin'));

        $this->assertTrue($this->usersRepo($this->usersResource(), allowAdminPromotion: true)->makeAdmin('john@example.com'));
    }

    public function test_protected_users_cannot_be_suspended()
    {
        $this->expectExceptionCode(6);
        try {
            $this->usersRepo($this->usersResource())->suspend('1001');
        } finally {
            $this->assertSame([], $this->callsOf('update'));
        }
    }

    public function test_protected_users_cannot_be_renamed()
    {
        $repo = $this->usersRepo($this->usersResource());

        // Updating other fields, or "renaming" to the same address, is fine
        $repo->update('ROOT@example.com', new UserDTO('root@example.com', givenName: 'Root'));
        $this->assertCount(1, $this->callsOf('update'));

        $this->expectExceptionCode(6);
        $repo->update('root@example.com', new UserDTO('renamed@example.com'));
    }

    public function test_protected_groups_cannot_be_renamed()
    {
        $groups = new class {
            public function get($groupKey)
            {
                return new Directory\Group(['email' => 'rootgroup@example.com', 'id' => 'g-1']);
            }

            public function update($groupKey, $group)
            {
                throw new \LogicException('should not be called');
            }
        };

        $repo = new GroupsRepository($this->services(['groups' => $groups]), 'example.com', null, ['rootgroup@example.com']);

        $this->expectExceptionCode(6);
        $repo->update('g-1', new GroupDTO('renamed@example.com'));
    }

    public function test_changes_are_audit_logged()
    {
        $logger = new class extends AbstractLogger {
            public array $records = [];

            public function log($level, $message, array $context = []): void
            {
                $this->records[] = [$level, (string)$message];
            }
        };

        $repo = $this->usersRepo($this->usersResource(), [], $logger, true);
        $repo->create(new UserDTO('new@example.com', 'New', 'User'));
        $repo->suspend('new@example.com');
        $repo->unsuspend('new@example.com');
        $repo->delete('new@example.com');
        $repo->makeAdmin('new@example.com');

        $this->assertSame([
            ['info', 'User created'],
            ['info', 'User suspended'],
            ['info', 'User unsuspended'],
            ['info', 'User deleted'],
            ['warning', 'User promoted to super admin'],
        ], $logger->records);
    }

    // --- 6. pagination ---

    public function test_all_walks_every_page()
    {
        $emails = array_map(fn (UserDTO $u) => $u->email, iterator_to_array($this->usersRepo($this->usersResource())->all(), false));

        $this->assertSame(['user1@example.com', 'user2@example.com'], $emails);
        $this->assertCount(2, $this->callsOf('list'));
    }
}
