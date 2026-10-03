# Migration Guide

## Upgrading from 3.x to 4.0

4.0 fixes several behaviour bugs and tightens security defaults. Most code keeps working, but review these changes:

### Requirements

- Laravel 12 or 13. Laravel 10 and 11 are end-of-life and no longer supported; stay on 3.x until you upgrade Laravel.
- PHP 8.2+ (Laravel 13 itself requires PHP 8.3+).

### `UserDTO` fields are nullable, and `null` means "don't change"

Previously `changePasswordAtNextLogin` defaulted to `true` and `suspended` to `false`, and `update()` silently dropped every `false` value. That meant **every `update()` call forced a password reset**, and nothing could be set back to `false`.

Now `givenName`, `familyName`, `changePasswordAtNextLogin` and `suspended` default to `null` and are only sent when set:

```php
// 3.x: also forced a password reset
// 4.0: only changes the first name
$workspace->users()->update($email, new UserDTO(email: $email, givenName: 'Johnny'));
```

- `create()` still defaults `changePasswordAtNextLogin` to `true`, and now requires `givenName` and `familyName` (validation error, code 4).
- If you read DTOs returned by the API, `givenName`/`familyName` are `null` (not `''`) when missing.
- `phone` and `title` are now actually sent. Note `title` replaces the user's `organizations` list with a single entry.
- An `update()` with no fields set throws `invalidArgument` (code 8).

### Exception codes reflect the real error

| Situation | 3.x | 4.0 |
|-----------|-----|-----|
| `get()` on anything that fails | 5 (not found) | 5 only for 404; 403 access denied; 429 rate limit; 3 other |
| Other methods, 404 from Google | 3 | 5 |
| 401/403 from Google | 3 | 403 |
| 429 / quota exceeded | 3 | 429 |
| Network failure | 3 | 7 |
| `create()` validation failure | 3 (wrapped) | 4 |

The original Google exception is always available via `$e->getPrevious()`.

### Safer defaults

- `makeAdmin()` throws access denied (403) unless `allow_admin_promotion` / `GOOGLE_WORKSPACE_ALLOW_ADMIN_PROMOTION` is `true`.
- Protected (`undeletable`) users cannot be suspended, and protected users and groups cannot be renamed (code 6).
- Default scopes are only `admin.directory.user` and `admin.directory.group`. If you published the config, your scopes are unchanged; otherwise add any others you use.
- Audit logging is on by default (`GOOGLE_WORKSPACE_LOGGING`), to your default channel unless `GOOGLE_WORKSPACE_LOG_CHANNEL` is set. In 3.x the logging settings were ignored and messages always went to the default channel.

### Settings that now take effect

`retry.max_attempts`, `retry.delay_ms`, `timeouts.connect` and `timeouts.read` were ignored in 3.x; they now configure retries and HTTP timeouts. `retry.max_attempts` is the total number of attempts (default 3).

### Type hints

`GoogleWorkspace::users()` and `groups()` now return `UsersRepositoryContract` and `GroupsRepositoryContract`. The concrete repositories implement them, so existing code is unaffected unless you extend `GoogleWorkspace`.

### New

- `users()->all()` / `groups()->all()` iterate every page lazily.
- `$workspace->batch()` sends bulk creates and membership changes as Google batch requests.

---

## Upgrading from v2 (wyattcast44/gsuite) to v3

This guide helps you upgrade from the old Google Workspace package to the modern v3 implementation.

## Table of Contents

1. [Installation](#installation)
2. [Configuration](#configuration)
3. [API Changes](#api-changes)
4. [Code Examples](#code-examples)
5. [Breaking Changes](#breaking-changes)
6. [Troubleshooting](#troubleshooting)

## Installation

### Remove Old Package

```bash
composer remove wyattcast44/gsuite
composer remove brickservers/gsuite
```

### Install New Package

```bash
composer require brickservers/gsuite
```

### Publish Configuration

```bash
php artisan vendor:publish --provider="BrickServers\GoogleWorkspace\GoogleWorkspaceServiceProvider" --tag=config
```

## Configuration

### Environment Variables

**Old (.env):**
```env
GOOGLE_SERVICE_ACCOUNT=admin@example.com
GSUITE_DOMAIN=example.com
```

**New (.env):**
```env
GOOGLE_WORKSPACE_SUBJECT=admin@example.com
GOOGLE_WORKSPACE_DOMAIN=example.com
GOOGLE_WORKSPACE_CREDENTIALS_PATH=/path/to/credentials.json
GOOGLE_WORKSPACE_LOGGING=true
```

### Config File

**Old:**
```php
// config/gsuite.php
return [
    'subject' => env('GOOGLE_SERVICE_ACCOUNT'),
    'credentials_path' => storage_path('credentials.json'),
    'domain' => env('GSUITE_DOMAIN'),
    // ...
];
```

**New:**
```php
// config/google-workspace.php
return [
    'subject' => env('GOOGLE_WORKSPACE_SUBJECT'),
    'credentials_path' => env('GOOGLE_WORKSPACE_CREDENTIALS_PATH'),
    'domain' => env('GOOGLE_WORKSPACE_DOMAIN'),
    // ...
];
```

## API Changes

### Users (formerly Accounts)

#### Create User

**Old:**
```php
GSuite::accounts()->create([
    ['first_name' => 'John', 'last_name' => 'Doe'],
    'email' => 'john.doe@example.com',
    'default_password' => 'password'
]);
```

**New:**
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

#### Get User

**Old:**
```php
$user = GSuite::accounts()->get('john.doe@example.com');
```

**New:**
```php
$user = app('google-workspace')->users()->get('john.doe@example.com');
```

#### List Users

**Old:**
```php
$users = GSuite::accounts()->all();
```

**New:**
```php
$result = app('google-workspace')->users()->list(maxResults: 500);
$users = $result['users'];
$nextPageToken = $result['nextPageToken']; // For pagination
```

#### Delete User

**Old:**
```php
GSuite::accounts()->delete('john.doe@example.com');
```

**New:**
```php
app('google-workspace')->users()->delete('john.doe@example.com');
```

#### New Methods (Not in old package)

```php
$workspace = app('google-workspace');

// Suspend/Unsuspend
$workspace->users()->suspend('john.doe@example.com');
$workspace->users()->unsuspend('john.doe@example.com');

// Aliases (email forwarding)
$workspace->users()->addAlias('john.doe@example.com', 'j.doe@example.com');
$workspace->users()->removeAlias('john.doe@example.com', 'j.doe@example.com');

// Promote to admin
$workspace->users()->makeAdmin('john.doe@example.com');
```

### Groups

#### Create Group

**Old:**
```php
GSuite::groups()->create('developers@example.com', 'Development Team', 'Developers');
```

**New:**
```php
use BrickServers\GoogleWorkspace\DTOs\GroupDTO;

$group = new GroupDTO(
    email: 'developers@example.com',
    name: 'Development Team',
    description: 'Developers',
);

app('google-workspace')->groups()->create($group);
```

#### Get Group

**Old:**
```php
$group = GSuite::groups()->get('developers@example.com');
```

**New:**
```php
$group = app('google-workspace')->groups()->get('developers@example.com');
```

#### List Groups

**Old:**
```php
$groups = GSuite::groups()->all();
```

**New:**
```php
$result = app('google-workspace')->groups()->list(maxResults: 200);
$groups = $result['groups'];
$nextPageToken = $result['nextPageToken'];
```

#### Delete Group

**Old:**
```php
GSuite::groups()->delete('developers@example.com');
```

**New:**
```php
app('google-workspace')->groups()->delete('developers@example.com');
```

#### New Methods (Not in old package)

```php
$workspace = app('google-workspace');

// Manage members
$workspace->groups()->addMember('developers@example.com', 'john.doe@example.com');
$workspace->groups()->removeMember('developers@example.com', 'john.doe@example.com');
```

## Code Examples

### Dependency Injection

**Old:**
```php
class UserController
{
    public function store()
    {
        GSuite::accounts()->create([...]);
    }
}
```

**New:**
```php
use BrickServers\GoogleWorkspace\GoogleWorkspace;

class UserController
{
    public function __construct(
        private readonly GoogleWorkspace $workspace,
    ) {}

    public function store()
    {
        $this->workspace->users()->create($user);
    }
}
```

### Error Handling

**Old:**
```php
try {
    GSuite::accounts()->create([...]);
} catch (Exception $e) {
    // Generic exception
}
```

**New:**
```php
use BrickServers\GoogleWorkspace\Exceptions\GoogleWorkspaceException;

try {
    $workspace->users()->create($user);
} catch (GoogleWorkspaceException $e) {
    if ($e->getCode() === 5) {
        // Resource not found
    } elseif ($e->getCode() === 4) {
        // Validation error
    }
}
```

### Pagination

**Old:**
```php
$all_users = GSuite::accounts()->all();
```

**New:**
```php
$workspace = app('google-workspace');
$allUsers = [];
$pageToken = null;

do {
    $result = $workspace->users()->list(
        maxResults: 100,
        pageToken: $pageToken,
    );
    
    $allUsers = array_merge($allUsers, $result['users']);
    $pageToken = $result['nextPageToken'];
} while ($pageToken);
```

## Breaking Changes

### Removed

- `GSuite::accounts()` - Use `$workspace->users()`
- `GSuite::groups()` - Use `$workspace->groups()`
- Action classes (CreateAccountAction, etc.)
- Command classes (use artisan commands - coming in v3.1)
- Cache-related traits
- `spatie/laravel-queueable-action` dependency

### Changed

- Namespace: `Wyattcast44\GSuite\*` → `BrickServers\GoogleWorkspace\*`
- Config file: `gsuite.php` → `google-workspace.php`
- Service Provider: `GSuiteServiceProvider` → `GoogleWorkspaceServiceProvider`
- Main class: `GSuite` → `GoogleWorkspace`
- Method: `accounts()` → `users()`
- Method: `all()` returns different structure (paginated)

### Updated

- PHP requirement: 8.1+ → 8.2+
- Laravel requirement: 8.0+ → 10.0+
- Google API Client: ^2.19 → ^2.20 or ^3.0

## Troubleshooting

### "Credentials not found"

Ensure your credentials file path is correct:
```env
GOOGLE_WORKSPACE_CREDENTIALS_PATH=/path/to/credentials.json
```

### "Subject not set"

The subject must be set to an admin email:
```env
GOOGLE_WORKSPACE_SUBJECT=admin@yourdomain.com
```

### Validation errors

The new package has strict validation. Check:
- Email format and domain
- Password length (8-100 characters)
- Field lengths

### API errors

Enable logging to debug:
```env
GOOGLE_WORKSPACE_LOGGING=true
```

Check logs in `storage/logs/laravel.log`

## Support

For issues or questions:

1. Check the [README.md](README.md)
2. Open an issue on [GitHub](https://github.com/brickservers/gsuite)

## Need to Keep Old Version?

If you need to keep the old package alongside the new one, use version constraints:

```bash
composer require "wyattcast44/gsuite:^2.0"
composer require "brickservers/gsuite:^3.0"
```

Then use different aliases/service names in your code.
