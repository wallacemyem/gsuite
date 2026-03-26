<?php

namespace BrickServers\GoogleWorkspace;

use BrickServers\GoogleWorkspace\DTOs\UserDTO;
use BrickServers\GoogleWorkspace\DTOs\GroupDTO;
use BrickServers\GoogleWorkspace\Enums\UserProjection;
use BrickServers\GoogleWorkspace\Enums\UserViewType;
use BrickServers\GoogleWorkspace\Exceptions\GoogleWorkspaceException;

/**
 * Google Workspace Usage Examples
 * 
 * This file serves as a reference for common operations.
 * Copy and adapt these examples for your use cases.
 */
class ExamplesAndUsage
{
    /**
     * Initialize the workspace
     */
    public static function initialize()
    {
        $workspace = app('google-workspace');
        return $workspace;
    }

    /**
     * Example: Create User
     */
    public static function exampleCreateUser()
    {
        $workspace = self::initialize();

        try {
            $user = new UserDTO(
                email: 'john.doe@example.com',
                givenName: 'John',
                familyName: 'Doe',
                password: 'SecurePassword123!',
                changePasswordAtNextLogin: true,
            );

            $created = $workspace->users()->create($user);
            echo "User created: {$created->email}\n";

            return $created;
        } catch (GoogleWorkspaceException $e) {
            echo "Error: {$e->getMessage()}\n";
            return null;
        }
    }

    /**
     * Example: Get User
     */
    public static function exampleGetUser()
    {
        $workspace = self::initialize();

        try {
            $user = $workspace->users()->get(
                'john.doe@example.com',
                projection: UserProjection::FULL,
                viewType: UserViewType::ADMIN_VIEW,
            );

            echo "User found: {$user->email} ({$user->givenName} {$user->familyName})\n";
            return $user;
        } catch (GoogleWorkspaceException $e) {
            echo "Error: {$e->getMessage()}\n";
            return null;
        }
    }

    /**
     * Example: List Users with Pagination
     */
    public static function exampleListUsers()
    {
        $workspace = self::initialize();

        try {
            $page = 1;
            $pageToken = null;

            do {
                $result = $workspace->users()->list(
                    maxResults: 100,
                    pageToken: $pageToken,
                );

                echo "Page {$page}: " . count($result['users']) . " users\n";

                foreach ($result['users'] as $user) {
                    echo "  - {$user->email}\n";
                }

                $pageToken = $result['nextPageToken'];
                $page++;
            } while ($pageToken);
        } catch (GoogleWorkspaceException $e) {
            echo "Error: {$e->getMessage()}\n";
        }
    }

    /**
     * Example: Update User
     */
    public static function exampleUpdateUser()
    {
        $workspace = self::initialize();

        try {
            $updates = new UserDTO(
                email: 'john.doe@example.com',
                givenName: 'Johnny',
                familyName: 'Doe Updated',
                title: 'Senior Developer',
            );

            $updated = $workspace->users()->update(
                'john.doe@example.com',
                $updates,
            );

            echo "User updated: {$updated->email}\n";
            return $updated;
        } catch (GoogleWorkspaceException $e) {
            echo "Error: {$e->getMessage()}\n";
            return null;
        }
    }

    /**
     * Example: Suspend User
     */
    public static function exampleSuspendUser()
    {
        $workspace = self::initialize();

        try {
            $suspended = $workspace->users()->suspend('john.doe@example.com');
            echo "User suspended: {$suspended->email}\n";
            return $suspended;
        } catch (GoogleWorkspaceException $e) {
            echo "Error: {$e->getMessage()}\n";
            return null;
        }
    }

    /**
     * Example: Manage Aliases
     */
    public static function exampleManagerAliases()
    {
        $workspace = self::initialize();

        try {
            // Add alias
            $workspace->users()->addAlias(
                'john.doe@example.com',
                'j.doe@example.com',
            );
            echo "Alias added: j.doe@example.com\n";

            // Remove alias
            $workspace->users()->removeAlias(
                'john.doe@example.com',
                'j.doe@example.com',
            );
            echo "Alias removed: j.doe@example.com\n";
        } catch (GoogleWorkspaceException $e) {
            echo "Error: {$e->getMessage()}\n";
        }
    }

    /**
     * Example: Create Group
     */
    public static function exampleCreateGroup()
    {
        $workspace = self::initialize();

        try {
            $group = new GroupDTO(
                email: 'developers@example.com',
                name: 'Development Team',
                description: 'All developers in the organization',
            );

            $created = $workspace->groups()->create($group);
            echo "Group created: {$created->email}\n";
            return $created;
        } catch (GoogleWorkspaceException $e) {
            echo "Error: {$e->getMessage()}\n";
            return null;
        }
    }

    /**
     * Example: Add Member to Group
     */
    public static function exampleAddGroupMember()
    {
        $workspace = self::initialize();

        try {
            $workspace->groups()->addMember(
                'developers@example.com',
                'john.doe@example.com',
            );
            echo "Member added to group\n";
        } catch (GoogleWorkspaceException $e) {
            echo "Error: {$e->getMessage()}\n";
        }
    }

    /**
     * Example: Error Handling
     */
    public static function exampleErrorHandling()
    {
        $workspace = self::initialize();

        try {
            // This will fail
            $user = $workspace->users()->get('nonexistent@example.com');
        } catch (GoogleWorkspaceException $e) {
            switch ($e->getCode()) {
                case 5:
                    echo "Resource not found\n";
                    break;
                case 4:
                    echo "Validation error\n";
                    break;
                case 403:
                    echo "Access denied\n";
                    break;
                default:
                    echo "API error: " . $e->getMessage() . "\n";
            }
        }
    }

    /**
     * Example: Using Dependency Injection
     */
    public static function exampleDependencyInjection()
    {
        // In your controller or service class
        class MyService
        {
            public function __construct(
                private readonly GoogleWorkspace $workspace,
            ) {}

            public function createUser(array $data)
            {
                $user = new UserDTO(
                    email: $data['email'],
                    givenName: $data['first_name'],
                    familyName: $data['last_name'],
                );

                return $this->workspace->users()->create($user);
            }
        }
    }
}
