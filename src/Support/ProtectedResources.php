<?php

namespace BrickServers\GoogleWorkspace\Support;

use BrickServers\GoogleWorkspace\Exceptions\GoogleWorkspaceException;
use BrickServers\GoogleWorkspace\Services\GoogleServicesFactory;

/**
 * Safety rules shared by the repositories and the generated API wrappers:
 * protected users and groups cannot be deleted, renamed or (for users) suspended,
 * and super admin rights can only be granted when explicitly allowed.
 *
 * Lookups always go through the admin services factory, so the rules hold even
 * when the caller is impersonating another user.
 */
class ProtectedResources
{
    private array $users;

    private array $groups;

    public function __construct(
        private readonly GoogleServicesFactory $services,
        array $users = [],
        array $groups = [],
        private readonly bool $allowAdminPromotion = false,
    ) {
        $this->users = array_map('strtolower', $users);
        $this->groups = array_map('strtolower', $groups);
    }

    public function allowsAdminPromotion(): bool
    {
        return $this->allowAdminPromotion;
    }

    /**
     * The API accepts a primary email (any case), an alias or the immutable user ID as
     * the key, so resolve the user and check every identifier against the protected list.
     */
    public function isUserProtected(string $userKey, ?object &$user = null): bool
    {
        if (! $this->users) {
            return false;
        }

        if (in_array(strtolower($userKey), $this->users, true)) {
            return true;
        }

        $user = $this->services->directory()->users->get($userKey, []);

        return $this->matches($this->users, [$user->primaryEmail ?? null, $user->id ?? null], $user);
    }

    /**
     * Same as isUserProtected(), for groups.
     */
    public function isGroupProtected(string $groupKey, ?object &$group = null): bool
    {
        if (! $this->groups) {
            return false;
        }

        if (in_array(strtolower($groupKey), $this->groups, true)) {
            return true;
        }

        $group = $this->services->directory()->groups->get($groupKey);

        return $this->matches($this->groups, [$group->email ?? null, $group->id ?? null], $group);
    }

    public function assertUserDeletable(string $userKey): void
    {
        if ($this->isUserProtected($userKey)) {
            throw GoogleWorkspaceException::undeletableResource('User', $userKey);
        }
    }

    public function assertGroupDeletable(string $groupKey): void
    {
        if ($this->isGroupProtected($groupKey)) {
            throw GoogleWorkspaceException::undeletableResource('Group', $groupKey);
        }
    }

    public function assertUserSuspendable(string $userKey): void
    {
        if ($this->isUserProtected($userKey)) {
            throw GoogleWorkspaceException::protectedResource('suspend', 'User', $userKey);
        }
    }

    /**
     * Renaming a protected user would let it be deleted under its new address.
     */
    public function assertUserRenameAllowed(string $userKey, string $newEmail): void
    {
        if ($this->isUserProtected($userKey, $current)) {
            $current ??= $this->services->directory()->users->get($userKey, []);
            if (strtolower($current->primaryEmail ?? '') !== strtolower($newEmail)) {
                throw GoogleWorkspaceException::protectedResource('rename', 'User', $userKey);
            }
        }
    }

    public function assertGroupRenameAllowed(string $groupKey, string $newEmail): void
    {
        if ($this->isGroupProtected($groupKey, $current)) {
            $current ??= $this->services->directory()->groups->get($groupKey);
            if (strtolower($current->email ?? '') !== strtolower($newEmail)) {
                throw GoogleWorkspaceException::protectedResource('rename', 'Group', $groupKey);
            }
        }
    }

    public function assertAdminPromotionAllowed(): void
    {
        if (! $this->allowAdminPromotion) {
            throw GoogleWorkspaceException::accessDenied(
                'Admin promotion is disabled. Set google-workspace.allow_admin_promotion to true to enable it.'
            );
        }
    }

    /**
     * Apply the rules to a raw API call made through the generated wrappers.
     *
     * @param  string  $service  e.g. "directory"
     * @param  string  $resource  Google resource property, e.g. "users" or "users_aliases"
     * @param  string  $method  Google PHP method name, e.g. "delete"
     * @param  array<int, mixed>  $args  positional arguments
     */
    public function guard(string $service, string $resource, string $method, array $args): void
    {
        if ($service !== 'directory') {
            return;
        }

        $key = is_string($args[0] ?? null) ? $args[0] : null;
        $body = $args[1] ?? null;

        if ($key === null) {
            return;
        }

        switch ("{$resource}.{$method}") {
            case 'users.delete':
                $this->assertUserDeletable($key);
                break;
            case 'users.update':
            case 'users.patch':
                if (($body->suspended ?? null) === true) {
                    $this->assertUserSuspendable($key);
                }
                if (isset($body->primaryEmail)) {
                    $this->assertUserRenameAllowed($key, $body->primaryEmail);
                }
                break;
            case 'users.makeAdmin':
                if ($body->status ?? false) {
                    $this->assertAdminPromotionAllowed();
                }
                break;
            case 'groups.delete':
                $this->assertGroupDeletable($key);
                break;
            case 'groups.update':
            case 'groups.patch':
                if (isset($body->email)) {
                    $this->assertGroupRenameAllowed($key, $body->email);
                }
                break;
            case 'roleAssignments.insert':
                $this->guardRoleAssignment($key, $body);
                break;
        }
    }

    /**
     * Assigning the Super Admin role is the same as makeAdmin(), so it needs the same opt-in.
     */
    private function guardRoleAssignment(string $customer, mixed $assignment): void
    {
        if ($this->allowAdminPromotion || ! isset($assignment->roleId)) {
            return;
        }

        $role = $this->services->directory()->roles->get($customer, (string) $assignment->roleId);
        if ($role->isSuperAdminRole ?? false) {
            $this->assertAdminPromotionAllowed();
        }
    }

    private function matches(array $protected, array $identifiers, object $resource): bool
    {
        $identifiers = array_merge(
            $identifiers,
            (array) ($resource->aliases ?? []),
            (array) ($resource->nonEditableAliases ?? []),
        );

        foreach ($identifiers as $identifier) {
            if (is_string($identifier) && in_array(strtolower($identifier), $protected, true)) {
                return true;
            }
        }

        return false;
    }
}
