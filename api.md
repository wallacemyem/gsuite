# G-Suite API Doc

This is a basic outline of the package, its architecture, etc.

## Commands

### Accounts

-   `CreateAccount`: `gsuite:create-account`
-   `DeleteAccount`: `gsuite:delete-account`
-   `SuspendAccount`: `gsuite:suspend-account`
-   `UnsuspendAccount`: `gsuite:unsuspend-account`

## Actions

Actions are queueable classes that encapsulate logic and tasks.

### Accounts

-   `CreateAccountAction`
-   `DeleteAccountAction`
-   `SuspendAccountAction`
-   `UnsuspendAccountAction`

### Groups

-   `CreateGroupAction`: `gsuite:create-group`
-   `DeleteGroupAction`: `gsuite:delete-group`

## GSuite class

The base class, primary means of interacting with package. Provides access to
accounts and groups.

```php
GSuite::accounts(); // GSuiteAccounts::class

GSuite::groups(); // GSuiteGroups::class
```

## GSuiteAccounts class

The primary G-Suite accounts class, provides a wrapper around the accounts
repository. Presents a way to retrieve, create, delete accounts. When applicable
returns instances of `GSuiteAccount`.

```php
GSuiteAccounts::all() // Collection of GSuiteAccount::class

GSuiteAccounts::find($email) // GSuiteAccount::class

GSuiteAccounts::delete(array $emails) // ?

GSuiteAccounts::create(array $name, $email, $password); // GSuiteAccount::class
```

## GSuiteAccount class

Primary representation of a G-Suite account.

```php

// One way to get single account
$account = GSuiteAccounts::find('john.doe@domain.com');

// Another way
$account = GSuiteAccounts::all()->first();

$account->delete(); // Returns bool

$account->suspend() // Returns bool
```
