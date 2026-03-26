<?php

namespace BrickServers\GoogleWorkspace\Utilities;

use BrickServers\GoogleWorkspace\DTOs\UserDTO;
use BrickServers\GoogleWorkspace\DTOs\GroupDTO;
use BrickServers\GoogleWorkspace\Repositories\UsersRepository;
use BrickServers\GoogleWorkspace\Repositories\GroupsRepository;
use BrickServers\GoogleWorkspace\Exceptions\GoogleWorkspaceException;

/**
 * Batch Operations Utility
 * 
 * Helper for performing batch operations on multiple users/groups
 */
class BatchOperations
{
    public function __construct(
        private readonly UsersRepository $users,
        private readonly GroupsRepository $groups,
    ) {}

    /**
     * Create multiple users
     */
    public function createUsers(array $userDTOs): array
    {
        $results = ['success' => [], 'failed' => []];

        foreach ($userDTOs as $userDTO) {
            try {
                $created = $this->users->create($userDTO);
                $results['success'][] = $created;
            } catch (GoogleWorkspaceException $e) {
                $results['failed'][] = [
                    'user' => $userDTO->email,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Suspend multiple users
     */
    public function suspendUsers(array $emails): array
    {
        $results = ['success' => [], 'failed' => []];

        foreach ($emails as $email) {
            try {
                $this->users->suspend($email);
                $results['success'][] = $email;
            } catch (GoogleWorkspaceException $e) {
                $results['failed'][] = [
                    'email' => $email,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Add multiple members to a group
     */
    public function addGroupMembers(string $groupEmail, array $memberEmails): array
    {
        $results = ['success' => [], 'failed' => []];

        foreach ($memberEmails as $email) {
            try {
                $this->groups->addMember($groupEmail, $email);
                $results['success'][] = $email;
            } catch (GoogleWorkspaceException $e) {
                $results['failed'][] = [
                    'email' => $email,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Remove multiple members from a group
     */
    public function removeGroupMembers(string $groupEmail, array $memberEmails): array
    {
        $results = ['success' => [], 'failed' => []];

        foreach ($memberEmails as $email) {
            try {
                $this->groups->removeMember($groupEmail, $email);
                $results['success'][] = $email;
            } catch (GoogleWorkspaceException $e) {
                $results['failed'][] = [
                    'email' => $email,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }
}
