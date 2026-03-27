<?php

namespace BrickServers\GoogleWorkspace\Repositories;

use BrickServers\GoogleWorkspace\Services\GoogleServicesFactory;
use BrickServers\GoogleWorkspace\DTOs\GroupDTO;
use BrickServers\GoogleWorkspace\Exceptions\GoogleWorkspaceException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class GroupsRepository
{
    private LoggerInterface $logger;
    private array $undeletableGroups = [];

    public function __construct(
        private readonly GoogleServicesFactory $services,
        private readonly string $domain,
        ?LoggerInterface $logger = null,
        array $undeletableGroups = [],
    ) {
        $this->logger = $logger ?? new NullLogger();
        $this->undeletableGroups = $undeletableGroups;
    }

    public function create(GroupDTO $group): GroupDTO
    {
        try {
            $googleGroup = new \Google_Service_Directory_Group($group->toArray());
            $response = $this->services->directory()->groups->insert($googleGroup);
            $this->logger->info('Group created', ['email' => $group->email]);
            return GroupDTO::fromArray((array)$response);
        } catch (\Exception $e) {
            throw GoogleWorkspaceException::apiError("Failed to create group: {$e->getMessage()}", $e);
        }
    }

    public function get(string $groupKey): GroupDTO
    {
        try {
            $response = $this->services->directory()->groups->get($groupKey);
            return GroupDTO::fromArray((array)$response);
        } catch (\Exception $e) {
            throw GoogleWorkspaceException::resourceNotFound('Group', $groupKey);
        }
    }

    public function list(int $maxResults = 200, ?string $pageToken = null): array
    {
        try {
            $options = ['domain' => $this->domain, 'maxResults' => min($maxResults, 200)];
            if ($pageToken) {
                $options['pageToken'] = $pageToken;
            }

            $response = $this->services->directory()->groups->listGroups($options);
            $groups = [];
            foreach ($response->getGroups() ?? [] as $group) {
                $groups[] = GroupDTO::fromArray((array)$group);
            }

            return ['groups' => $groups, 'nextPageToken' => $response->getNextPageToken() ?? null];
        } catch (\Exception $e) {
            throw GoogleWorkspaceException::apiError("Failed to list groups: {$e->getMessage()}", $e);
        }
    }

    public function update(string $groupKey, GroupDTO $updates): GroupDTO
    {
        try {
            $googleGroup = new \Google_Service_Directory_Group(array_filter($updates->toArray()));
            $response = $this->services->directory()->groups->update($groupKey, $googleGroup);
            $this->logger->info('Group updated', ['groupKey' => $groupKey]);
            return GroupDTO::fromArray((array)$response);
        } catch (\Exception $e) {
            throw GoogleWorkspaceException::apiError("Failed to update group: {$e->getMessage()}", $e);
        }
    }

    public function delete(string $groupKey): bool
    {
        try {
            if (in_array($groupKey, $this->undeletableGroups)) {
                throw GoogleWorkspaceException::undeletableResource('Group', $groupKey);
            }
            $this->services->directory()->groups->delete($groupKey);
            $this->logger->info('Group deleted', ['groupKey' => $groupKey]);
            return true;
        } catch (GoogleWorkspaceException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw GoogleWorkspaceException::apiError("Failed to delete group: {$e->getMessage()}", $e);
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
            throw GoogleWorkspaceException::apiError("Failed to add member: {$e->getMessage()}", $e);
        }
    }

    public function removeMember(string $groupKey, string $userEmail): bool
    {
        try {
            $this->services->directory()->members->delete($groupKey, $userEmail);
            $this->logger->info('Member removed from group', ['groupKey' => $groupKey, 'email' => $userEmail]);
            return true;
        } catch (\Exception $e) {
            throw GoogleWorkspaceException::apiError("Failed to remove member: {$e->getMessage()}", $e);
        }
    }
}
