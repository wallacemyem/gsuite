# Google Workspace SDK for Laravel

[![Latest Version](https://img.shields.io/packagist/v/brickservers/gsuite.svg?style=flat-square)](https://packagist.org/packages/brickservers/gsuite)
[![Total Downloads](https://img.shields.io/packagist/dt/brickservers/gsuite.svg?style=flat-square)](https://packagist.org/packages/brickservers/gsuite)
[![License](https://img.shields.io/packagist/l/brickservers/gsuite.svg?style=flat-square)](LICENSE.md)
[![Tests](https://github.com/wallacemyem/gsuite/actions/workflows/laravel.yml/badge.svg)](https://github.com/wallacemyem/gsuite/actions/workflows/laravel.yml)

A Laravel package for managing Google Workspace (formerly G Suite). Friendly repositories for users and groups, plus every method of the Admin SDK Directory, Classroom, Calendar, Gmail and Drive APIs, with error handling, audit logging and safety rails built in.

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
- ✅ **Every Google API Method** - All 413 methods of the Directory, Classroom, Calendar, Gmail and Drive APIs
- ✅ **Act as Any User** - `asUser()` for Gmail settings, calendars and Drive files via domain-wide delegation
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
- ✅ **Mockable** - Repository contracts for dependency injection and testing

## Requirements

- PHP 8.2 or higher
- Laravel 12 or 13 (Laravel 13 needs PHP 8.3+)
- Google Workspace account with super admin access (to set up domain-wide delegation)
- Google Cloud project with the APIs you use enabled (Admin SDK at minimum)

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
3. Enable the **Admin SDK API**, plus the Classroom, Calendar, Gmail or Drive APIs if you use them
4. Create a service account and download its JSON key
5. Move the key to `storage/credentials.json` (or set `GOOGLE_WORKSPACE_CREDENTIALS_PATH`)
6. In the [Google Admin console](https://admin.google.com), go to **Security → Access and data control → API controls → Domain-wide delegation**, add the service account's client ID, and authorize the scopes from your config (and any you pass to `asUser()`)

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

The default scopes cover the users and groups repositories. If you use other parts of the API through `directory()`, `classroom()`, `calendar()`, `gmail()` or `drive()`, add the scopes they need and authorize them for the service account in the Google Admin console. Google's service classes define a constant for every scope, e.g. `Google\Service\Directory::ADMIN_DIRECTORY_ORGUNIT` or `Google\Service\Calendar::CALENDAR`.

## Usage

### Basic Setup

```php
use BrickServers\GoogleWorkspace\GoogleWorkspace;

// Via the service container
$workspace = app('google-workspace');

// Via the facade (auto-registered as GSuite)
GSuite::users()->get('john.doe@example.com');
GSuite::asUser('jane@example.com')->gmail()->usersLabels()->listUsersLabels('me');

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

### Full API Access

Every resource and method of the Directory, Classroom, Calendar, Gmail and Drive APIs is available through `directory()`, `classroom()`, `calendar()`, `gmail()` and `drive()`. Resource and method names match Google's PHP client (and its API reference), and arguments and return values are Google's own model classes:

```php
use Google\Service\Directory\OrgUnit;

// Org units, roles, domains, devices, schemas, buildings, ... (Directory API)
$workspace->directory()->orgunits()->insert('my_customer', new OrgUnit([
    'name' => 'Engineering',
    'parentOrgUnitPath' => '/',
]));
$roles = $workspace->directory()->roles()->listRoles('my_customer');
$workspace->directory()->users()->signOut('john.doe@example.com');
$workspace->directory()->twoStepVerification()->turnOff('john.doe@example.com');

// Classroom
$courses = $workspace->classroom()->courses()->listCourses(['teacherId' => 'teacher@example.com']);
```

Every call:
- turns Google errors into `GoogleWorkspaceException` with the codes listed under [Error Handling](#error-handling),
- writes a log entry for anything that changes data,
- applies the same protections as the repositories: protected users and groups can't be deleted, renamed or (users) suspended, and super admin can't be granted, through `makeAdmin` or a Super Admin role assignment, unless `allow_admin_promotion` is on.

For anything else, `->google()` returns the underlying `Google\Service\*` object.

#### Acting as a User

Gmail, Calendar and Drive mostly work on a particular user's data. `asUser()` impersonates that user through domain-wide delegation:

```php
use Google\Service\Gmail\VacationSettings;

$jane = $workspace->asUser('jane@example.com');

$jane->gmail()->usersSettings()->updateVacation('me', new VacationSettings([
    'enableAutoReply' => true,
    'responseSubject' => 'Out of office',
    'responseBodyPlainText' => 'Back on Monday.',
]));
$events = $jane->calendar()->events()->listEvents('primary');
$files = $jane->drive()->files()->listFiles(['pageSize' => 50]);
```

The impersonated account needs the right scopes. By default `asUser()` uses the configured `scopes`; pass others as the second argument. Every scope must be authorized for the service account under **Security → API controls → Domain-wide delegation** in the Google Admin console. Google's service classes define constants for each scope:

```php
use Google\Service\Gmail;

$jane = $workspace->asUser('jane@example.com', [Gmail::GMAIL_SETTINGS_BASIC, Gmail::GMAIL_SETTINGS_SHARING]);
```

`users()`, `groups()` and `batch()` always act as the configured admin, even after `asUser()`.

#### Paginating Any List

```php
foreach ($workspace->directory()->mobiledevices()->paginate('listMobiledevices', 'my_customer') as $device) {
    // every device, across all pages
}

foreach ($jane->drive()->files()->paginate('listFiles', ['q' => "mimeType = 'application/pdf'"]) as $file) {
    // ...
}
```

Pass the list method's name followed by its normal arguments.

### Batch Operations

Bulk user creation and group membership changes are sent as Google batch requests (up to 1000 calls per HTTP request). Each item succeeds or fails on its own:

```php
$batch = $workspace->batch();

$result = $batch->createUsers([$userA, $userB]);
$result = $batch->addGroupMembers('developers@example.com', ['a@example.com', 'b@example.com']);
$result = $batch->removeGroupMembers('developers@example.com', ['c@example.com']);
$result = $batch->suspendUsers(['a@example.com']); // one call per user, so protected users are checked

// ['success' => [...], 'failed' => [['email' => ..., 'error' => ...], ...]]
// createUsers() returns UserDTOs in 'success' and uses a 'user' key in 'failed'
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
| 1 | Invalid configuration (e.g. missing subject, or an API method missing from an outdated `google/apiclient-services`) |
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

Common OAuth scopes:

```php
ApiScope::DIRECTORY_USER
ApiScope::DIRECTORY_GROUP
ApiScope::CLASSROOM_COURSES
ApiScope::CALENDAR
ApiScope::GMAIL_COMPOSE
ApiScope::DRIVE
// ... and more
```

For the complete list, use the constants on Google's service classes (e.g. `Google\Service\Gmail::GMAIL_SETTINGS_SHARING`).

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

Every change made through the package is logged: repository actions (creates, updates, deletes, suspensions, aliases, membership changes, admin promotions) and every non-read call through the API wrappers, recorded as `Google Workspace API change` with the method (e.g. `gmail.usersSettings.updateVacation`), its ID arguments and, after `asUser()`, the impersonated account. Request bodies are not logged.

Logs go to your application's default channel. Send them elsewhere with `GOOGLE_WORKSPACE_LOG_CHANNEL`, or turn logging off with `GOOGLE_WORKSPACE_LOGGING=false`.

## Supported APIs

Every method of these Google APIs is available, with Google's own documentation on each method:

| API | Accessor | Resources | Methods |
|-----|----------|-----------|---------|
| Admin SDK Directory | `directory()` | 28 | 128 |
| Classroom | `classroom()` | 24 | 104 |
| Calendar | `calendar()` | 8 | 38 |
| Gmail | `gmail()` | 15 | 79 |
| Drive | `drive()` | 14 | 64 |

Users and groups also have the friendlier repositories documented above.

## Security Best Practices

1. **Never commit credentials.json** - Add to `.gitignore`
2. **Use environment variables** - Store sensitive data in `.env`
3. **Limit API scopes** - Only request and authorize the scopes your app needs; domain-wide delegation lets the service account act as *any* user for those scopes
4. **Keep audit logging on** - Every change is logged by default
5. **Use protected resources** - Add critical accounts/groups to the `undeletable` list (emails, aliases or IDs)
6. **Leave admin promotion off** - Only enable `allow_admin_promotion` if your app really needs it
7. **Authorize your own users** - The package acts with full admin rights, and `asUser()` can reach any user's mail, calendar and files; check in your app who may trigger each action

## Performance Tips

1. **Iterate with `all()` / `paginate()`** - Pages are fetched lazily instead of loading everything at once
2. **Cache results** - Store frequently accessed data
3. **Use `batch()`** - Bulk creates and membership changes need far fewer HTTP requests
4. **Tune retries** - Raise `retry.max_attempts` for large jobs that hit rate limits

## Contributing

The API wrappers in `src/Api/{Directory,Classroom,Calendar,Gmail,Drive}` are generated. Don't edit them by hand: run `composer generate-api` after updating `google/apiclient-services` to pick up new Google endpoints.

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
- Built on the [Google APIs Client Library for PHP](https://github.com/googleapis/google-api-php-client)
