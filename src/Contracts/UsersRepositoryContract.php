<?php

namespace BrickServers\GoogleWorkspace\Contracts;

use BrickServers\GoogleWorkspace\DTOs\UserDTO;
use BrickServers\GoogleWorkspace\Enums\UserProjection;
use BrickServers\GoogleWorkspace\Enums\UserViewType;
use Generator;

interface UsersRepositoryContract
{
    public function create(UserDTO $user): UserDTO;

    /**
     * Check a user is valid for creation (email in domain, names, password length).
     *
     * @throws \BrickServers\GoogleWorkspace\Exceptions\GoogleWorkspaceException
     */
    public function validate(UserDTO $user): void;

    public function get(
        string $userKey,
        UserProjection $projection = UserProjection::FULL,
        UserViewType $viewType = UserViewType::ADMIN_VIEW,
    ): UserDTO;

    /**
     * @return array{users: UserDTO[], nextPageToken: ?string}
     */
    public function list(int $maxResults = 500, ?string $pageToken = null): array;

    /**
     * Lazily iterate over every user in the domain, fetching pages as needed.
     *
     * @return Generator<int, UserDTO>
     */
    public function all(int $pageSize = 500): Generator;

    public function update(string $userKey, UserDTO $updates): UserDTO;

    public function delete(string $userKey): bool;

    public function suspend(string $userKey): UserDTO;

    public function unsuspend(string $userKey): UserDTO;

    public function addAlias(string $userKey, string $alias): bool;

    public function removeAlias(string $userKey, string $alias): bool;

    public function makeAdmin(string $userKey): bool;
}
