# G-Suite Admin SDK Wrapper

[![Latest Version on Packagist](https://img.shields.io/packagist/v/brickservers/gsuite.svg?style=flat-square)](https://packagist.org/packages/brickservers/gsuite)
[![Total Downloads](https://poser.pugx.org/brickservers/gsuite/downloads)](//packagist.org/packages/brickservers/gsuite)

This is a wrapper around the
[Google Admin SDK](https://developers.google.com/admin-sdk/). It allows you to
manage your G-Suite account in your Laravel application. There is an
[example application](https://github.com/WyattCast44/gsuite-package-example) if
you'd like to check that out.

**_Warning: under active development, use at your own risk. A version will be
tagged when ready for testing._**

## Installation

You can install the package via composer:

```bash
composer require brickservers/gsuite
```

Once the install has finished, publish the configuration file

```bash
php artisan vendor:publish
```

### Configuration

1. Set your account to impersonate

```php
// .env
GOOGLE_SERVICE_ACCOUNT=email@domain.com
```

2. Update the `credentials_path`, ensure you add your credentials
   file to your `.gitignore`. You can download this file from the [Google admin console](https://admin.google.com)

```php
'credentials_path' => storage_path('credentials.json'),
```

3. Set your domain

```php
// .env
GSUITE_DOMAIN=example.com
```

4. Change cache settings as desired in config file

5. Add any accounts, alias, or groups that you want to disable the ability to
   delete. Used to ensure no one can delete your service account. You can still
   delete them manually via the G-Suite Administrator interface.

## Usage

### G-Suite Account Management

```php
// Create a new G-Suite account
GSuite::accounts()->create([
    [
        'first_name' => 'John',
        'last_name' => 'Doe',
    ],
    'email' => 'john.doe@email.com',
    'default_password' => 'password'
]);

// Get a G-Suite account
GSuite::accounts()->get('john.doe@example.com');

// Get a collection of all G-Suite accounts in your domain
GSuite::accounts()->all();

// Delete a G-Suite account
GSuite::accounts()->delete('john.doe@example.com');

// Suspend a G-Suite account
GSuite::accounts()->suspend('john.doe@example.com');

// Add an alias to a G-Suite account
GSuite::accounts()->alias('john.doe@example.com', 'support@example.com');
```

### G-Suite Group Management

```php
// Create a new G-Suite group
GSuite::groups()->create('group.email@example.com', 'Group Name', 'Group description');

// Get a G-Suite group
GSuite::groups()->get('group.email@example.com');

// Get a collection of all G-Suite groups in your domain
GSuite::groups()->all();

// Delete a G-Suite group
GSuite::groups()->delete('group.example@example.com');

// Add a member to a G-Suite group
GSuite::groups()->addMember('group.email@example.com', 'john.doe@example.com');
```

### Caching

By default `accounts` and `groups` are cached. If you choose not to cache
results, request times will be lengthy. The cache will automatically flush when
you delete, insert, or update resources. You can flush the cache at any time,
see examples below.

```php
// Flush accounts and groups cache
GSuite::flushCache();

// Flush only accounts cache
GSuite::accounts()->flushCache();

// Flush only groups cache
GSuite::groups()->flushCache();

// Via the CLI
php artisan gsuite:flush-cache
```

### Other Resources

You can use the `GoogleServicesClient` class to get api clients for other google
services, for example let's say you wanted to manage your domain's
[organizational units](https://developers.google.com/admin-sdk/directory/v1/guides/manage-org-units).

You can get a api client for the org units like so:

```php
$client = GSuiteServicesClient::getService('orgunit');
```

### Testing

```bash
composer test
```

### Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed
recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

### Security

If you discover any security related issues, please email
wyatt.castaneda@gmail.com instead of using the issue tracker.

## Credits

-   [Wyatt](https://github.com/wyattcast44) #first started the package
-   [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more
information.

## Laravel Package Boilerplate

This package was generated using the
[Laravel Package Boilerplate](https://laravelpackageboilerplate.com).
