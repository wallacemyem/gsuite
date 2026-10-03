# Google Workspace SDK for Laravel

[![Latest Version](https://img.shields.io/packagist/v/brickservers/gsuite.svg?style=flat-square)](https://packagist.org/packages/brickservers/gsuite)
[![Total Downloads](https://img.shields.io/packagist/dt/brickservers/gsuite.svg?style=flat-square)](https://packagist.org/packages/brickservers/gsuite)
[![License](https://img.shields.io/packagist/l/brickservers/gsuite.svg?style=flat-square)](LICENSE.md)



A modern, fully-featured Laravel package for managing Google Workspace (formerly G Suite) using the latest Google Admin SDK API. Supports user management, group management, directory operations, and more.

## Sponsor

Support the development of this package:
[![Sponsor](https://img.shields.io/badge/Sponsor-%E2%9D%A4-pink?logo=github)](https://github.com/sponsors/wallacemyem)
<p align="center">
  <a href="https://paystack.shop/pay/wallace">
    <img src="https://upload.wikimedia.org/wikipedia/commons/1/1f/Paystack.png" alt="Sponsor with Paystack" width="200">
  </a>
</p>

## Features

- ✅ **Modern PHP 8.2+** - Uses latest language features (readonly types, enums, named arguments)
- ✅ **User Management** - Create, read, update, delete, suspend, and manage user accounts
- ✅ **Group Management** - Full group CRUD operations and member management
- ✅ **Batch Operations** - Bulk creates and membership changes sent as Google batch requests
- ✅ **Pagination Helpers** - Iterate every user or group with `all()`
- ✅ **Type-Safe DTOs** - Partial updates: only the fields you set are sent
- ✅ **Precise Error Handling** - Not found, access denied and rate limits are reported as such
- ✅ **Audit Logging** - Every change is logged through PSR-3 / Laravel logging
- ✅ **Safety Rails** - Protected accounts and groups, opt-in admin promotion
- ✅ **Retries & Timeouts** - Configurable backoff for transient failures
- ✅ **Laravel 12 & 13** - Supports the current Laravel releases
- ✅ **Extensible** - Easy to extend with custom services

## Requirements

- PHP 8.2 or higher
- Laravel 12 or 13 (Laravel 13 needs PHP 8.3+)
- Google Workspace account with admin access
- Google Cloud Project with Admin SDK API enabled

## Installation

```bash
composer require brickservers/gsuite
```

### Publish Configuration

```bash
php artisan vendor:publish --provider="BrickServers\GoogleWorkspace\GoogleWorkspaceServiceProvider" --tag=config
```

This will publish the configuration file to `config/google-workspace.php`.

## Configuration

### 1. Set Up Google Cloud Project

1. Go to [Google Cloud Console](https://console.cloud.google.com)
2. Create a new project
3. Enable the "Google Admin SDK API"
4. Create a service account
5. Download the credentials JSON file
6. Move the file to `storage/credentials.json` (or configure the path)

### 2. Configure Environment Variables

Add these to your `.env` file:

```env
GOOGLE_WORKSPACE_CREDENTIALS_PATH=/path/to/credentials.json
GOOGLE_WORKSPACE_DOMAIN=example.com
GOOGLE_WORKSPACE_SUBJECT=admin@example.com

# Optional
GOOGLE_WORKSPACE_UNDELETABLE_USERS=admin@example.com,it@example.com
GOOGLE_WORKSPACE_UNDELETABLE_GROUPS=all-staff@example.com
GOOGLE_WORKSPACE_ALLOW_ADMIN_PROMOTION=false
GOOGLE_WORKSPACE_RETRY_MAX_ATTEMPTS=3
GOOGLE_WORKSPACE_RETRY_DELAY_MS=100
GOOGLE_WORKSPACE_CONNECT_TIMEOUT=10
GOOGLE_WORKSPACE_READ_TIMEOUT=60
GOOGLE_WORKSPACE_LOGGING=true
GOOGLE_WORKSPACE_LOG_CHANNEL=
```

`GOOGLE_WORKSPACE_SUBJECT` is required: it is the admin account the service account impersonates through domain-wide delegation.

### 3. Update Configuration

Edit `config/google-workspace.php` to customize settings:

```php
'domain' => env('GOOGLE_WORKSPACE_DOMAIN', 'example.com'),
'credentials_path' => env('GOOGLE_WORKSPACE_CREDENTIALS_PATH', storage_path('credentials.json')),
'subject' => env('GOOGLE_WORKSPACE_SUBJECT'),
'scopes' => [
    'https://www.googleapis.com/auth/admin.directory.user',
    'https://www.googleapis.com/auth/admin.directory.group',
],
```

The default scopes cover the users and groups repositories. Only add more (see the `ApiScope` enum) if you call other APIs through `services()`, and authorize the same scopes for the service account in the Google Admin console.

## Usage

### Basic Setup

```php
use BrickServers\GoogleWorkspace\GoogleWorkspace;

// Via facade or service container
$workspace = app('google-workspace');

// Or using dependency injection
public function __construct(GoogleWorkspace $workspace)
{
    $this->workspace = $workspace;
}

// Or inject just the repository you need (handy for mocking in tests)
use BrickServers\GoogleWorkspace\Contracts\UsersRepositoryContract;

public function __construct(UsersRepositoryContract $users) { /* ... */ }
```

### User Management

#### Create a User

```php
use BrickServers\GoogleWorkspace\DTOs\UserDTO;

$user = new UserDTO(
    email: 'john.doe@example.com',
    givenName: 'John',
    familyName: 'Doe',
    password: 'SecurePassword123!',
    phone: '+1 555 0100',
    title: 'Engineer',
);

$created = $workspace->users()->create($user);
```

`givenName` and `familyName` are required when creating. New users must change their password at next login unless you pass `changePasswordAtNextLogin: false`.

#### Get a User

```php
$user = $workspace->users()->get('john.doe@example.com');

// With projection and view type
use BrickServers\GoogleWorkspace\Enums\UserProjection;
use BrickServers\GoogleWorkspace\Enums\UserViewType;

$user = $workspace->users()->get(
    'john.doe@example.com',
    projection: UserProjection::FULL,
    viewType: UserViewType::ADMIN_VIEW,
);
```

#### List Users

```php
$result = $workspace->users()->list(maxResults: 100);

$users = $result['users']; // Array of UserDTO
$nextPageToken = $result['nextPageToken']; // For pagination

// Or iterate every user; pages are fetched lazily as you go
foreach ($workspace->users()->all() as $user) {
    echo $user->email;
}
```

#### Update a User

```php
Only the fields you set are sent; everything else is left unchanged.

```php
// Change just the first name
$workspace->users()->update('john.doe@example.com', new UserDTO(
    email: 'john.doe@example.com',
    givenName: 'Johnny',
));

// Pass an empty email to leave the address alone; false values are sent as-is
$workspace->users()->update('john.doe@example.com', new UserDTO(
    email: '',
    changePasswordAtNextLogin: false,
));
```

Setting `email` to a different address renames the user. Protected users cannot be renamed.

#### Delete a User

```php
$workspace->users()->delete('john.doe@example.com');
```

Users in the `undeletable` list cannot be deleted, renamed or suspended, whether you refer to them by email (any case), alias or user ID.

#### Suspend/Unsuspend a User

```php
// Suspend
$workspace->users()->suspend('john.doe@example.com');

// Unsuspend
$workspace->users()->unsuspend('john.doe@example.com');
```

#### Make a User a Super Admin

Disabled by default because it grants full control of the domain. Enable it with `GOOGLE_WORKSPACE_ALLOW_ADMIN_PROMOTION=true`; otherwise an access denied exception (code 403) is thrown.

```php
$workspace->users()->makeAdmin('john.doe@example.com');
```

#### Manage User Aliases

```php
// Add alias
$workspace->users()->addAlias('john.doe@example.com', 'j.doe@example.com');

// Remove alias
$workspace->users()->removeAlias('john.doe@example.com', 'j.doe@example.com');
```

### Group Management

#### Create a Group

```php
use BrickServers\GoogleWorkspace\DTOs\GroupDTO;

$group = new GroupDTO(
    email: 'developers@example.com',
    name: 'Development Team',
    description: 'All developers in the organization',
);

$created = $workspace->groups()->create($group);
```

#### Get a Group

```php
$group = $workspace->groups()->get('developers@example.com');
```

#### List Groups

```php
$result = $workspace->groups()->list(maxResults: 100);

$groups = $result['groups']; // Array of GroupDTO
$nextPageToken = $result['nextPageToken']; // For pagination

// Or iterate every group
foreach ($workspace->groups()->all() as $group) {
    echo $group->email;
}
```

#### Update a Group

```php
// Fields left null are not changed
$updates = new GroupDTO(
    email: 'developers@example.com',
    name: 'Dev Team',
);

$updated = $workspace->groups()->update('developers@example.com', $updates);
```

#### Delete a Group

```php
$workspace->groups()->delete('developers@example.com');
```

#### Manage Group Members

```php
// Add member
$workspace->groups()->addMember('developers@example.com', 'john.doe@example.com');

// Remove member
$workspace->groups()->removeMember('developers@example.com', 'john.doe@example.com');
```

### Batch Operations

Bulk user creation and group membership changes are sent as Google batch requests (up to 1000 calls per HTTP request). Each item succeeds or fails on its own:

```php
$batch = $workspace->batch();

$result = $batch->createUsers([$userA, $userB]);
$result = $batch->addGroupMembers('developers@example.com', ['a@example.com', 'b@example.com']);
$result = $batch->removeGroupMembers('developers@example.com', ['c@example.com']);
$result = $batch->suspendUsers(['a@example.com']); // one call per user, so protected users are checked

// ['success' => [...], 'failed' => [['email' => ..., 'error' => ...], ...]]
```

## Error Handling

The package throws `GoogleWorkspaceException` for all API errors:

```php
use BrickServers\GoogleWorkspace\Exceptions\GoogleWorkspaceException;

try {
    $workspace->users()->create($user);
} catch (GoogleWorkspaceException $e) {
    if ($e->getCode() === 5) {
        // Resource not found
    } elseif ($e->getCode() === 429) {
        // Still rate limited after automatic retries
    }

    // The original Google error is available for details
    $original = $e->getPrevious();

    logger()->error('Google Workspace API error: ' . $e->getMessage());
}
```

### Exception Codes

| Code | Meaning |
|------|---------|
| 1 | Invalid configuration (e.g. missing subject) |
| 2 | Credentials file not found |
| 3 | Other API error |
| 4 | Validation error |
| 5 | Resource not found (Google returned 404) |
| 6 | Protected resource (cannot delete, rename or suspend) |
| 7 | Connection error |
| 8 | Invalid argument (e.g. an update with no fields) |
| 403 | Access denied (Google returned 401/403, or admin promotion disabled) |
| 429 | Rate limit or quota exceeded |

Transient failures (5xx, rate limits, network errors) are retried automatically according to the `retry` settings before an exception is thrown.

## Data Transfer Objects (DTOs)

### UserDTO

```php
new UserDTO(
    email: string,
    givenName: ?string = null,
    familyName: ?string = null,
    password: ?string = null,
    changePasswordAtNextLogin: ?bool = null,
    suspended: ?bool = null,
    phone: ?string = null,   // sent as the primary work phone
    title: ?string = null,   // sent as the primary organization title
    customSchemas: ?array = null,
)
```

`null` means "not set": the field is left out of API requests. An empty `email` is also left out.

### GroupDTO

```php
new GroupDTO(
    email: string,
    name: ?string = null,
    description: ?string = null,
)
```

## Enums

### UserProjection

```php
UserProjection::BASIC    // Basic information only
UserProjection::FULL     // Full user information
UserProjection::CUSTOM   // Custom schema information
```

### UserViewType

```php
UserViewType::ADMIN_VIEW      // Admin perspective
UserViewType::DOMAIN_PUBLIC   // Public domain view
```

### ApiScope

Predefined OAuth scopes for different APIs:

```php
ApiScope::DIRECTORY_USER
ApiScope::DIRECTORY_GROUP
ApiScope::CLASSROOM_COURSES
ApiScope::CALENDAR
ApiScope::GMAIL_COMPOSE
ApiScope::DRIVE
// ... and more
```

## Migration from Old Package

See [MIGRATION.md](MIGRATION.md) for full upgrade guides, including the breaking changes in 4.0. If you're upgrading from `wyattcast44/gsuite`:

### Changes Summary

1. **Namespace Changed**: `Wyattcast44\GSuite` → `BrickServers\GoogleWorkspace`
2. **New DTOs**: Use `UserDTO` and `GroupDTO` instead of raw arrays
3. **Type-Safe**: All methods now have proper type hints
4. **Better Errors**: Custom exception types for better error handling
5. **Modern PHP**: Uses PHP 8.2+ features (enums, readonly types, named arguments)

### Migration Steps

#### Before (Old Package)

```php
GSuite::accounts()->create([
    ['first_name' => 'John', 'last_name' => 'Doe'],
    'email' => 'john.doe@example.com',
    'default_password' => 'password'
]);
```

#### After (New Package)

```php
use BrickServers\GoogleWorkspace\DTOs\UserDTO;

$user = new UserDTO(
    email: 'john.doe@example.com',
    givenName: 'John',
    familyName: 'Doe',
    password: 'password',
);

app('google-workspace')->users()->create($user);
```

## Testing

```bash
composer test
```

Run with coverage:

```bash
composer test-coverage
```

## Logging

Every change made through the package (creates, updates, deletes, suspensions, aliases, membership changes, admin promotions) is logged, by default to your application's default log channel. Send it elsewhere with `GOOGLE_WORKSPACE_LOG_CHANNEL`, or turn it off with `GOOGLE_WORKSPACE_LOGGING=false`.

## Supported APIs

- ✅ **Directory API** - Users and groups, with the repositories documented above
- 🔧 **Classroom, Calendar, Gmail, Drive** - Authenticated Google service clients only, with no helper methods yet. Add the matching scopes, then call the Google client directly:

```php
$calendar = $workspace->services()->calendar(); // Google\Service\Calendar
$events = $calendar->events->listEvents('primary');
```

## Security Best Practices

1. **Never commit credentials.json** - Add to `.gitignore`
2. **Use environment variables** - Store sensitive data in `.env`
3. **Limit API scopes** - Only request scopes your app needs
4. **Keep audit logging on** - Every change is logged by default
5. **Use protected resources** - Add critical accounts/groups to the `undeletable` list (emails, aliases or IDs)
6. **Leave admin promotion off** - Only enable `allow_admin_promotion` if your app really needs it
7. **Authorize your own users** - The package acts with full admin rights; check in your app who may trigger each action

## Performance Tips

1. **Iterate with `all()`** - Pages are fetched lazily instead of loading everything at once
2. **Cache results** - Store frequently accessed data
3. **Use `batch()`** - Bulk creates and membership changes need far fewer HTTP requests
4. **Tune retries** - Raise `retry.max_attempts` for large jobs that hit rate limits

## Contributing

Contributions are welcome! Please include tests, and run `composer test`, `composer lint` and `composer analyze` before opening a pull request (CI runs all three).

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history and breaking changes.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Support

For issues, questions, or suggestions, please open an issue on [GitHub](https://github.com/wallacemyem/gsuite).

## Credits

- Modern rewrite by [BrickServers Team](https://brickng.com)
- Original package by [Wyatt Cast](https://github.com/WyattCast44/gsuite)
- Built with the [Google Admin SDK](https://developers.google.com/admin-sdk)
