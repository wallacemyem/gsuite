<?php

namespace BrickServers\GoogleWorkspace\Repositories;

use BrickServers\GoogleWorkspace\Contracts\GroupsRepositoryContract;
use BrickServers\GoogleWorkspace\DTOs\GroupDTO;
use BrickServers\GoogleWorkspace\Exceptions\GoogleWorkspaceException;
use BrickServers\GoogleWorkspace\Services\GoogleServicesFactory;
use BrickServers\GoogleWorkspace\Support\ProtectedResources;
use Generator;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class GroupsRepository implements GroupsRepositoryContract
{
    private LoggerInterface $logger;

    private ProtectedResources $protection;

    public function __construct(
        private readonly GoogleServicesFactory $services,
        private readonly string $domain,
        ?LoggerInterface $logger = null,
        array $undeletableGroups = [],
        ?ProtectedResources $protection = null,
    ) {
        $this->logger = $logger ?? new NullLogger;
        $this->protection = $protection ?? new ProtectedResources($services, [], $undeletableGroups);
    }

    public function create(GroupDTO $group): GroupDTO
    {
        try {
            $googleGroup = new \Google_Service_Directory_Group($group->toArray());
            $response = $this->services->directory()->groups->insert($googleGroup);
            $this->logger->info('Group created', ['email' => $group->email]);

            return GroupDTO::fromArray((array) $response);
        } catch (\Exception $e) {
            throw GoogleWorkspaceException::fromGoogle($e, 'create group', 'Group', $group->email);
        }
    }

    public function get(string $groupKey): GroupDTO
    {
        try {
            $response = $this->services->directory()->groups->get($groupKey);

            return GroupDTO::fromArray((array) $response);
        } catch (\Exception $e) {
            throw GoogleWorkspaceException::fromGoogle($e, 'get group', 'Group', $groupKey);
        }
    }

    public function list(int $maxResults = 200, ?string $pageToken = null): array
    {
        try {
            $options = ['domain' => $this->domain, 'maxResults' => max(1, min($maxResults, 200))];
            if ($pageToken) {
                $options['pageToken'] = $pageToken;
            }

            $response = $this->services->directory()->groups->listGroups($options);
            $groups = [];
            foreach ($response->getGroups() ?? [] as $group) {
                $groups[] = GroupDTO::fromArray((array) $group);
            }

            return ['groups' => $groups, 'nextPageToken' => $response->getNextPageToken() ?? null];
        } catch (\Exception $e) {
            throw GoogleWorkspaceException::fromGoogle($e, 'list groups', 'Group');
        }
    }

    public function all(int $pageSize = 200): Generator
    {
        $pageToken = null;

        do {
            $page = $this->list($pageSize, $pageToken);
            yield from $page['groups'];
            $pageToken = $page['nextPageToken'];
        } while ($pageToken);
    }

    public function update(string $groupKey, GroupDTO $updates): GroupDTO
    {
        try {
            $payload = $updates->toArray();
            if (! $payload) {
                throw GoogleWorkspaceException::invalidArgument('updates', 'No fields to update');
            }

            if (isset($payload['email'])) {
                $this->protection->assertGroupRenameAllowed($groupKey, $payload['email']);
            }

            $googleGroup = new \Google_Service_Directory_Group($payload);
            $response = $this->services->directory()->groups->update($groupKey, $googleGroup);
            $this->logger->info('Group updated', ['groupKey' => $groupKey, 'fields' => array_keys($payload)]);

            return GroupDTO::fromArray((array) $response);
        } catch (\Exception $e) {
            throw GoogleWorkspaceException::fromGoogle($e, 'update group', 'Group', $groupKey);
        }
    }

    public function delete(string $groupKey): bool
    {
        try {
            $this->protection->assertGroupDeletable($groupKey);
            $this->services->directory()->groups->delete($groupKey);
            $this->logger->info('Group deleted', ['groupKey' => $groupKey]);

            return true;
        } catch (\Exception $e) {
            throw GoogleWorkspaceException::fromGoogle($e, 'delete group', 'Group', $groupKey);
        }
    }

    public function addMember(string $groupKey, string $userEmail): bool
    {
        try {
            $member = new \Google_Service_Directory_Member(['email' => $userEmail]);
            $this->services->directory()->members->insert($groupKey, $member);
            $this->logger->info('Member added to group', ['groupKey' => $groupKey, 'email' => $userEmail]);

            return true;
        } catch (\Exception $e) {
            throw GoogleWorkspaceException::fromGoogle($e, 'add member', 'Group', $groupKey);
        }
    }

    public function removeMember(string $groupKey, string $userEmail): bool
    {
        try {
            $this->services->directory()->members->delete($groupKey, $userEmail);
            $this->logger->info('Member removed from group', ['groupKey' => $groupKey, 'email' => $userEmail]);

            return true;
        } catch (\Exception $e) {
            throw GoogleWorkspaceException::fromGoogle($e, 'remove member', 'Group', $groupKey);
        }
    }
}
