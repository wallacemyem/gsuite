# API Documentation

## GoogleWorkspace API Reference

The complete API reference for the Google Workspace SDK.

### Users Repository API

#### `create(UserDTO $user): UserDTO`

Create a new user in Google Workspace.

```php
$user = new UserDTO(
    email: 'john.doe@example.com',
    givenName: 'John',
    familyName: 'Doe',
    password: 'SecurePassword123!',
);

$created = $workspace->users()->create($user);
```

#### `get(string $userKey, UserProjection $projection = FULL, UserViewType $viewType = ADMIN_VIEW): UserDTO`

Retrieve a user by email or ID.

```php
$user = $workspace->users()->get('john.doe@example.com');
```

#### `list(int $maxResults = 500, ?string $pageToken = null): array`

List users with pagination support.

```php
$result = $workspace->users()->list(maxResults: 100);
$users = $result['users'];
$nextPageToken = $result['nextPageToken'];
```

#### `update(string $userKey, UserDTO $updates): UserDTO`

Update user properties.

```php
$updates = new UserDTO(
    email: 'john.doe@example.com',
    givenName: 'Johnny',
    familyName: 'Doe',
);

$updated = $workspace->users()->update('john.doe@example.com', $updates);
```

#### `delete(string $userKey): bool`

Delete a user account.

```php
$workspace->users()->delete('john.doe@example.com');
```

#### `suspend(string $userKey): UserDTO`

Suspend a user account.

```php
$workspace->users()->suspend('john.doe@example.com');
```

#### `unsuspend(string $userKey): UserDTO`

Reactivate a suspended user.

```php
$workspace->users()->unsuspend('john.doe@example.com');
```

#### `addAlias(string $userKey, string $alias): bool`

Add an email alias.

```php
$workspace->users()->addAlias('john.doe@example.com', 'j.doe@example.com');
```

#### `removeAlias(string $userKey, string $alias): bool`

Remove an email alias.

```php
$workspace->users()->removeAlias('john.doe@example.com', 'j.doe@example.com');
```

#### `makeAdmin(string $userKey): bool`

Promote a user to admin.

```php
$workspace->users()->makeAdmin('john.doe@example.com');
```

### Groups Repository API

#### `create(GroupDTO $group): GroupDTO`

Create a new group.

```php
$group = new GroupDTO(
    email: 'developers@example.com',
    name: 'Development Team',
    description: 'All developers',
);

$created = $workspace->groups()->create($group);
```

#### `get(string $groupKey): GroupDTO`

Retrieve a group.

```php
$group = $workspace->groups()->get('developers@example.com');
```

#### `list(int $maxResults = 200, ?string $pageToken = null): array`

List groups.

```php
$result = $workspace->groups()->list(maxResults: 50);
```

#### `update(string $groupKey, GroupDTO $updates): GroupDTO`

Update group properties.

```php
$updates = new GroupDTO(
    email: 'developers@example.com',
    name: 'Dev Team',
);

$updated = $workspace->groups()->update('developers@example.com', $updates);
```

#### `delete(string $groupKey): bool`

Delete a group.

```php
$workspace->groups()->delete('developers@example.com');
```

#### `addMember(string $groupKey, string $userEmail): bool`

Add a member to a group.

```php
$workspace->groups()->addMember('developers@example.com', 'john.doe@example.com');
```

#### `removeMember(string $groupKey, string $userEmail): bool`

Remove a member from a group.

```php
$workspace->groups()->removeMember('developers@example.com', 'john.doe@example.com');
```

## DTOs

### UserDTO

```php
readonly class UserDTO {
    public string $email;
    public string $givenName;
    public string $familyName;
    public ?string $password;
    public bool $changePasswordAtNextLogin;
    public bool $suspended;
    public ?string $phone;
    public ?string $title;
    public ?array $customSchemas;

    public static function fromArray(array $data): self;
    public function toArray(): array;
}
```

### GroupDTO

```php
readonly class GroupDTO {
    public string $email;
    public ?string $name;
    public ?string $description;

    public static function fromArray(array $data): self;
    public function toArray(): array;
}
```

## Enums

### UserProjection

- `BASIC` - Basic user information
- `FULL` - Full user information
- `CUSTOM` - Custom schema information

### UserViewType

- `ADMIN_VIEW` - Administrator view
- `DOMAIN_PUBLIC` - Public domain view

### ApiScope

Directory API, Classroom, Calendar, Gmail, and Drive scopes.

## Exception Handling

```php
use BrickServers\GoogleWorkspace\Exceptions\GoogleWorkspaceException;

try {
    $workspace->users()->create($user);
} catch (GoogleWorkspaceException $e) {
    // Handle error
}
```

See README.md for full documentation and examples.
