# Changelog

All notable changes to `gsuite` will be documented in this file

## 4.0.0 - Unreleased

See [MIGRATION.md](MIGRATION.md#upgrading-from-3x-to-40) for upgrade notes.

### Changed
- Requires Laravel 12 or 13; support for end-of-life Laravel 10 and 11 is dropped
- Tested against Laravel 13 with Testbench 11 and PHPUnit 12; dev tooling moved to PHPStan 2

### Security
- Protected (`undeletable`) users and groups can no longer be deleted via a different-case email, an alias or their ID
- Protected users cannot be suspended (including via `update()`); protected users and groups cannot be renamed
- Assigning the Super Admin role is subject to `allow_admin_promotion`, like `makeAdmin()`
- `makeAdmin()` is disabled unless `allow_admin_promotion` is enabled
- Default scopes reduced to users and groups
- All user changes are audit logged

### Fixed
- `update()` no longer forces a password reset or blanks unset name fields, and can set values to `false`
- `unsuspend()` actually unsuspends; `suspend()` no longer blanks names or forces a password reset
- `phone` and `title` are sent to the API
- Errors map to the real cause (not found, access denied, rate limit, connection) instead of all being "not found" or generic
- `retry`, `timeouts` and `logging` settings are applied
- A missing admin subject gives a clear configuration error
- The `GSuite` facade alias points at an existing class

### Added
- Every method of the Directory, Classroom, Calendar, Gmail and Drive APIs (413 methods across 89 resources) via `directory()`, `classroom()`, `calendar()`, `gmail()` and `drive()`, generated from google/apiclient-services with error mapping, audit logging and the safety rules
- `asUser()` to act as another user through domain-wide delegation
- `paginate()` on every API resource to iterate list methods across pages
- `all()` pagination helpers on users and groups
- Real Google batch requests in `BatchOperations`, available via `$workspace->batch()`
- `UsersRepositoryContract` and `GroupsRepositoryContract` for dependency injection and mocking

## 1.0.0 - 201X-XX-XX

- initial release
