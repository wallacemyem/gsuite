# Google Workspace SDK for Laravel

[![Latest Version](https://img.shields.io/packagist/v/brickservers/gsuite.svg?style=flat-square)](https://packagist.org/packages/brickservers/gsuite)
[![Total Downloads](https://img.shields.io/packagist/dt/brickservers/gsuite.svg?style=flat-square)](https://packagist.org/packages/brickservers/gsuite)
[![License](https://img.shields.io/packagist/l/brickservers/gsuite.svg?style=flat-square)](LICENSE.md)



A modern, fully-featured Laravel package for managing Google Workspace (formerly G Suite) using the latest Google Admin SDK API. Supports user management, group management, directory operations, and more.

## Sponsor

Support the development of this package:

<p align="center">
  <a href="https://paystack.shop/pay/wallace">
    <img src="https://upload.wikimedia.org/wikipedia/commons/1/1f/Paystack.png" alt="Sponsor with Paystack" width="200">
  </a>
    [![Sponsor](https://img.shields.io/badge/Sponsor-%E2%9D%A4-pink?logo=github)](https://github.com/sponsors/wallacemyem)
</p>

## Features

- ✅ **Modern PHP 8.2+** - Uses latest language features (readonly types, enums, named arguments)
- ✅ **User Management** - Create, read, update, delete, suspend, and manage user accounts
- ✅ **Group Management** - Full group CRUD operations and member management
- ✅ **Directory API** - Complete access to the Google Directory API
- ✅ **Type-Safe DTOs** - Data transfer objects for type safety
- ✅ **Comprehensive Error Handling** - Custom exceptions with detailed error information
- ✅ **Logging Support** - Built-in PSR-3 logging for all operations
- ✅ **Fluent Interface** - Simple, clean API for all operations
- ✅ **Laravel 10-13 Support** - Compatible with all modern Laravel versions
- ✅ **Extensible** - Easy to extend with custom services

## Requirements

- PHP 8.2 or higher
- Laravel 10.0 or higher
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
GOOGLE_WORKSPACE_LOGGING=true
```

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
    changePasswordAtNextLogin: true,
);

$created = $workspace->users()->create($user);
```

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
```

#### Update a User

```php
$updates = new UserDTO(
    email: 'john.doe@example.com',
    givenName: 'Johnny',
    familyName: 'Doe',
);

$updated = $workspace->users()->update('john.doe@example.com', $updates);
```

#### Delete a User

```php
$workspace->users()->delete('john.doe@example.com');
```

#### Suspend/Unsuspend a User

```php
// Suspend
$workspace->users()->suspend('john.doe@example.com');

// Unsuspend
$workspace->users()->unsuspend('john.doe@example.com');
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
```

#### Update a Group

```php
$updates = new GroupDTO(
    email: 'developers@example.com',
    name: 'Dev Team',
    description: 'Updated description',
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

## Error Handling

The package throws `GoogleWorkspaceException` for all API errors:

```php
use BrickServers\GoogleWorkspace\Exceptions\GoogleWorkspaceException;

try {
    $workspace->users()->create($user);
} catch (GoogleWorkspaceException $e) {
    // Handle specific errors
    if ($e->getCode() === 5) {
        // Resource not found
    }
    
    logger()->error('Google Workspace API error: ' . $e->getMessage());
}
```

### Exception Types

- `GoogleWorkspaceException::validationError()` - Validation failed
- `GoogleWorkspaceException::resourceNotFound()` - Resource doesn't exist
- `GoogleWorkspaceException::undeletableResource()` - Protected resource
- `GoogleWorkspaceException::apiError()` - General API error
- `GoogleWorkspaceException::accessDenied()` - Insufficient permissions
- `GoogleWorkspaceException::missingCredentials()` - Credentials not configured

## Data Transfer Objects (DTOs)

### UserDTO

```php
new UserDTO(
    email: string,
    givenName: string,
    familyName: string,
    password: ?string = null,
    changePasswordAtNextLogin: bool = true,
    suspended: bool = false,
    phone: ?string = null,
    title: ?string = null,
    customSchemas: ?array = null,
)
```

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

If you're upgrading from `wyattcast44/gsuite` or `brickservers/gsuite`:

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

## Debugging

Enable logging in your `.env`:

```env
GOOGLE_WORKSPACE_LOGGING=true
```

View logs in `storage/logs/laravel.log` to see all API operations.

## Supported APIs

- ✅ Google Admin Directory API
- ✅ Google Classroom API (ready)
- ✅ Google Calendar API (ready)  
- ✅ Google Gmail API (ready)
- ✅ Google Drive API (ready)

## Security Best Practices

1. **Never commit credentials.json** - Add to `.gitignore`
2. **Use environment variables** - Store sensitive data in `.env`
3. **Limit API scopes** - Only request scopes your app needs
4. **Enable logging** - Monitor all API operations
5. **Use protected resources** - Add critical accounts/groups to `undeletable` list
6. **Validate input** - DTOs provide validation out of the box

## Performance Tips

1. **Use pagination** - Always set `maxResults` parameter
2. **Cache results** - Store frequently accessed data
3. **Batch operations** - Group multiple API calls when possible
4. **Enable logging selectively** - Disable in production if not needed

## Contributing

Contributions are welcome! Please follow Laravel coding standards and include tests.

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
