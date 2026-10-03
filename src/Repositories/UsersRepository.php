<?php

namespace BrickServers\GoogleWorkspace\Repositories;

use BrickServers\GoogleWorkspace\Contracts\UsersRepositoryContract;
use BrickServers\GoogleWorkspace\DTOs\UserDTO;
use BrickServers\GoogleWorkspace\Enums\UserProjection;
use BrickServers\GoogleWorkspace\Enums\UserViewType;
use BrickServers\GoogleWorkspace\Exceptions\GoogleWorkspaceException;
use BrickServers\GoogleWorkspace\Services\GoogleServicesFactory;
use Generator;
use Google\Service\Directory\Alias;
use Google\Service\Directory\UserMakeAdmin;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class UsersRepository implements UsersRepositoryContract
{
    private array $undeletableUsers = [];

    private LoggerInterface $logger;

    public function __construct(
        private readonly GoogleServicesFactory $services,
        private readonly string $domain,
        array $undeletableUsers = [],
        ?LoggerInterface $logger = null,
        private readonly bool $allowAdminPromotion = false,
    ) {
        $this->undeletableUsers = array_map('strtolower', $undeletableUsers);
        $this->logger = $logger ?? new NullLogger;
    }

    public function create(UserDTO $user): UserDTO
    {
        try {
            $this->validate($user);

            $payload = $user->toArray();
            $payload['changePasswordAtNextLogin'] ??= true;

            $googleUser = new \Google_Service_Directory_User($payload);
            $response = $this->services->directory()->users->insert($googleUser);
            $this->logger->info('User created', ['email' => $user->email]);

            return UserDTO::fromArray((array) $response);
        } catch (\Exception $e) {
            throw GoogleWorkspaceException::fromGoogle($e, 'create user', 'User', $user->email);
        }
    }

    public function get(
        string $userKey,
        UserProjection $projection = UserProjection::FULL,
        UserViewType $viewType = UserViewType::ADMIN_VIEW,
    ): UserDTO {
        try {
            $response = $this->services->directory()->users->get($userKey, [
                'projection' => $projection->value,
                'viewType' => $viewType->value,
            ]);

            return UserDTO::fromArray((array) $response);
        } catch (\Exception $e) {
            throw GoogleWorkspaceException::fromGoogle($e, 'get user', 'User', $userKey);
        }
    }

    public function list(int $maxResults = 500, ?string $pageToken = null): array
    {
        try {
            $options = [
                'domain' => $this->domain,
                'maxResults' => max(1, min($maxResults, 500)),
                'projection' => 'full',
            ];
            if ($pageToken) {
                $options['pageToken'] = $pageToken;
            }

            $response = $this->services->directory()->users->listUsers($options);
            $users = [];
            foreach ($response->getUsers() ?? [] as $user) {
                $users[] = UserDTO::fromArray((array) $user);
            }

            return [
                'users' => $users,
                'nextPageToken' => $response->getNextPageToken() ?? null,
            ];
        } catch (\Exception $e) {
            throw GoogleWorkspaceException::fromGoogle($e, 'list users', 'User');
        }
    }

    public function all(int $pageSize = 500): Generator
    {
        $pageToken = null;

        do {
            $page = $this->list($pageSize, $pageToken);
            yield from $page['users'];
            $pageToken = $page['nextPageToken'];
        } while ($pageToken);
    }

    public function update(string $userKey, UserDTO $updates): UserDTO
    {
        try {
            $payload = $updates->toArray();
            if (! $payload) {
                throw GoogleWorkspaceException::invalidArgument('updates', 'No fields to update');
            }

            // Renaming a protected user would let it be deleted under its new address
            if (isset($payload['primaryEmail']) && $this->isProtected($userKey, $current)) {
                $current ??= $this->services->directory()->users->get($userKey, []);
                if (strtolower($current->primaryEmail ?? '') !== strtolower($payload['primaryEmail'])) {
                    throw GoogleWorkspaceException::protectedResource('rename', 'User', $userKey);
                }
            }

            return $this->patch($userKey, $payload, 'User updated');
        } catch (\Exception $e) {
            throw GoogleWorkspaceException::fromGoogle($e, 'update user', 'User', $userKey);
        }
    }

    public function delete(string $userKey): bool
    {
        try {
            if ($this->isProtected($userKey)) {
                throw GoogleWorkspaceException::undeletableResource('User', $userKey);
            }
            $this->services->directory()->users->delete($userKey);
            $this->logger->info('User deleted', ['userKey' => $userKey]);

            return true;
        } catch (\Exception $e) {
            throw GoogleWorkspaceException::fromGoogle($e, 'delete user', 'User', $userKey);
        }
    }

    public function suspend(string $userKey): UserDTO
    {
        try {
            if ($this->isProtected($userKey)) {
                throw GoogleWorkspaceException::protectedResource('suspend', 'User', $userKey);
            }

            return $this->patch($userKey, ['suspended' => true], 'User suspended');
        } catch (\Exception $e) {
            throw GoogleWorkspaceException::fromGoogle($e, 'suspend user', 'User', $userKey);
        }
    }

    public function unsuspend(string $userKey): UserDTO
    {
        try {
            return $this->patch($userKey, ['suspended' => false], 'User unsuspended');
        } catch (\Exception $e) {
            throw GoogleWorkspaceException::fromGoogle($e, 'unsuspend user', 'User', $userKey);
        }
    }

    public function addAlias(string $userKey, string $alias): bool
    {
        try {
            $userAlias = new Alias(['alias' => $alias]);
            $this->services->directory()->users_aliases->insert($userKey, $userAlias);
            $this->logger->info('User alias added', ['userKey' => $userKey, 'alias' => $alias]);

            return true;
        } catch (\Exception $e) {
            throw GoogleWorkspaceException::fromGoogle($e, 'add alias', 'User', $userKey);
        }
    }

    public function removeAlias(string $userKey, string $alias): bool
    {
        try {
            $this->services->directory()->users_aliases->delete($userKey, $alias);
            $this->logger->info('User alias removed', ['userKey' => $userKey, 'alias' => $alias]);

            return true;
        } catch (\Exception $e) {
            throw GoogleWorkspaceException::fromGoogle($e, 'remove alias', 'User', $userKey);
        }
    }

    public function makeAdmin(string $userKey): bool
    {
        if (! $this->allowAdminPromotion) {
            throw GoogleWorkspaceException::accessDenied(
                'Admin promotion is disabled. Set google-workspace.allow_admin_promotion to true to enable it.'
            );
        }

        try {
            $makeAdminRequest = new UserMakeAdmin;
            $makeAdminRequest->setStatus(true);
            $this->services->directory()->users->makeAdmin($userKey, $makeAdminRequest);
            $this->logger->warning('User promoted to super admin', ['userKey' => $userKey]);

            return true;
        } catch (\Exception $e) {
            throw GoogleWorkspaceException::fromGoogle($e, 'promote user', 'User', $userKey);
        }
    }

    private function patch(string $userKey, array $fields, string $logMessage): UserDTO
    {
        $googleUser = new \Google_Service_Directory_User($fields);
        $response = $this->services->directory()->users->update($userKey, $googleUser);
        $this->logger->info($logMessage, ['userKey' => $userKey, 'fields' => array_keys($fields)]);

        return UserDTO::fromArray((array) $response);
    }

    /**
     * The API accepts a primary email (any case), an alias or the immutable user ID as
     * the key, so resolve the user and check every identifier against the protected list.
     */
    private function isProtected(string $userKey, ?object &$user = null): bool
    {
        if (! $this->undeletableUsers) {
            return false;
        }

        if (in_array(strtolower($userKey), $this->undeletableUsers, true)) {
            return true;
        }

        $user = $this->services->directory()->users->get($userKey, []);
        $identifiers = array_merge(
            [$user->primaryEmail ?? null, $user->id ?? null],
            (array) ($user->aliases ?? []),
            (array) ($user->nonEditableAliases ?? []),
        );

        foreach ($identifiers as $identifier) {
            if (is_string($identifier) && in_array(strtolower($identifier), $this->undeletableUsers, true)) {
                return true;
            }
        }

        return false;
    }

    public function validate(UserDTO $user): void
    {
        $this->validateEmail($user->email);
        $this->validatePassword($user->password);

        if (! $user->givenName || ! $user->familyName) {
            throw GoogleWorkspaceException::validationError('name', 'Given name and family name are required');
        }
    }

    private function validateEmail(string $email): void
    {
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw GoogleWorkspaceException::validationError('email', 'Invalid email format');
        }
        if (! str_ends_with(strtolower($email), '@'.strtolower($this->domain))) {
            throw GoogleWorkspaceException::validationError('email', "Email must be in domain @{$this->domain}");
        }
    }

    private function validatePassword(?string $password): void
    {
        if (! $password) {
            return;
        }
        if (strlen($password) < 8 || strlen($password) > 100) {
            throw GoogleWorkspaceException::validationError('password', 'Password must be 8-100 characters');
        }
    }
}
