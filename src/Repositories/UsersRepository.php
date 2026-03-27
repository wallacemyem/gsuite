<?php

namespace BrickServers\GoogleWorkspace\Repositories;

use BrickServers\GoogleWorkspace\Services\GoogleServicesFactory;
use BrickServers\GoogleWorkspace\DTOs\UserDTO;
use BrickServers\GoogleWorkspace\Exceptions\GoogleWorkspaceException;
use BrickServers\GoogleWorkspace\Enums\UserProjection;
use BrickServers\GoogleWorkspace\Enums\UserViewType;

class UsersRepository
{
    private array $undeletableUsers = [];

    public function __construct(
        private readonly GoogleServicesFactory $services,
        private readonly string $domain,
        array $undeletableUsers = [],
    ) {
        $this->undeletableUsers = $undeletableUsers;
    }

    public function create(UserDTO $user): UserDTO
    {
        try {
            $this->validateEmail($user->email);
            $this->validatePassword($user->password);
            $googleUser = new \Google_Service_Directory_User($user->toArray());
            $response = $this->services->directory()->users->insert($googleUser);
            return UserDTO::fromArray((array)$response);
        } catch (\Exception $e) {
            throw GoogleWorkspaceException::apiError("Failed to create user: {$e->getMessage()}", $e);
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
            return UserDTO::fromArray((array)$response);
        } catch (\Exception $e) {
            throw GoogleWorkspaceException::resourceNotFound('User', $userKey);
        }
    }

    public function list(int $maxResults = 500, ?string $pageToken = null): array
    {
        try {
            $options = [
                'domain' => $this->domain,
                'maxResults' => min($maxResults, 500),
                'projection' => 'full',
            ];
            if ($pageToken) {
                $options['pageToken'] = $pageToken;
            }

            $response = $this->services->directory()->users->listUsers($options);
            $users = [];
            foreach ($response->getUsers() ?? [] as $user) {
                $users[] = UserDTO::fromArray((array)$user);
            }
            return [
                'users' => $users,
                'nextPageToken' => $response->getNextPageToken() ?? null,
            ];
        } catch (\Exception $e) {
            throw GoogleWorkspaceException::apiError("Failed to list users: {$e->getMessage()}", $e);
        }
    }

    public function update(string $userKey, UserDTO $updates): UserDTO
    {
        try {
            $googleUser = new \Google_Service_Directory_User(array_filter($updates->toArray()));
            $response = $this->services->directory()->users->update($userKey, $googleUser);
            return UserDTO::fromArray((array)$response);
        } catch (\Exception $e) {
            throw GoogleWorkspaceException::apiError("Failed to update user: {$e->getMessage()}", $e);
        }
    }

    public function delete(string $userKey): bool
    {
        try {
            if (in_array($userKey, $this->undeletableUsers)) {
                throw GoogleWorkspaceException::undeletableResource('User', $userKey);
            }
            $this->services->directory()->users->delete($userKey);
            return true;
        } catch (GoogleWorkspaceException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw GoogleWorkspaceException::apiError("Failed to delete user: {$e->getMessage()}", $e);
        }
    }

    public function suspend(string $userKey): UserDTO
    {
        $user = new UserDTO($userKey, '', '', suspended: true);
        return $this->update($userKey, $user);
    }

    public function unsuspend(string $userKey): UserDTO
    {
        $user = new UserDTO($userKey, '', '', suspended: false);
        return $this->update($userKey, $user);
    }

    public function addAlias(string $userKey, string $alias): bool
    {
        try {
            $userAlias = new \Google\Service\Directory\Alias(['alias' => $alias]);
            $this->services->directory()->users_aliases->insert($userKey, $userAlias);
            return true;
        } catch (\Exception $e) {
            throw GoogleWorkspaceException::apiError("Failed to add alias: {$e->getMessage()}", $e);
        }
    }

    public function removeAlias(string $userKey, string $alias): bool
    {
        try {
            $this->services->directory()->users_aliases->delete($userKey, $alias);
            return true;
        } catch (\Exception $e) {
            throw GoogleWorkspaceException::apiError("Failed to remove alias: {$e->getMessage()}", $e);
        }
    }

    public function makeAdmin(string $userKey): bool
    {
        try {
            $makeAdminRequest = new \Google\Service\Directory\UserMakeAdmin();
            $makeAdminRequest->setStatus(true);
            $this->services->directory()->users->makeAdmin($userKey, $makeAdminRequest);
            return true;
        } catch (\Exception $e) {
            throw GoogleWorkspaceException::apiError("Failed to promote user: {$e->getMessage()}", $e);
        }
    }

    private function validateEmail(string $email): void
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw GoogleWorkspaceException::validationError('email', 'Invalid email format');
        }
        if (!str_ends_with($email, '@' . $this->domain)) {
            throw GoogleWorkspaceException::validationError('email', "Email must be in domain @{$this->domain}");
        }
    }

    private function validatePassword(?string $password): void
    {
        if (!$password) return;
        if (strlen($password) < 8 || strlen($password) > 100) {
            throw GoogleWorkspaceException::validationError('password', 'Password must be 8-100 characters');
        }
    }
}
