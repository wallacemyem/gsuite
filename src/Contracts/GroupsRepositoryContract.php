<?php

namespace BrickServers\GoogleWorkspace\Contracts;

use BrickServers\GoogleWorkspace\DTOs\GroupDTO;
use Generator;

interface GroupsRepositoryContract
{
    public function create(GroupDTO $group): GroupDTO;

    public function get(string $groupKey): GroupDTO;

    /**
     * @return array{groups: GroupDTO[], nextPageToken: ?string}
     */
    public function list(int $maxResults = 200, ?string $pageToken = null): array;

    /**
     * Lazily iterate over every group in the domain, fetching pages as needed.
     *
     * @return Generator<int, GroupDTO>
     */
    public function all(int $pageSize = 200): Generator;

    public function update(string $groupKey, GroupDTO $updates): GroupDTO;

    public function delete(string $groupKey): bool;

    public function addMember(string $groupKey, string $userEmail): bool;

    public function removeMember(string $groupKey, string $userEmail): bool;
}
