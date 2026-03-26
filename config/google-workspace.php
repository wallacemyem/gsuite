<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Credentials Path
    |--------------------------------------------------------------------------
    | Path to your Google Workspace service account credentials JSON file.
    | You can download this from the Google Cloud Console.
    |
    */
    'credentials_path' => env('GOOGLE_WORKSPACE_CREDENTIALS_PATH', storage_path('credentials.json')),

    /*
    |--------------------------------------------------------------------------
    | Domain
    |--------------------------------------------------------------------------
    | Your Google Workspace domain name (e.g., example.com)
    |
    */
    'domain' => env('GOOGLE_WORKSPACE_DOMAIN', 'example.com'),

    /*
    |--------------------------------------------------------------------------
    | Subject (Admin Account)
    |--------------------------------------------------------------------------
    | The email address of the admin account to impersonate for API requests.
    | This account must have admin privileges.
    |
    */
    'subject' => env('GOOGLE_WORKSPACE_SUBJECT'),

    /*
    |--------------------------------------------------------------------------
    | API Scopes
    |--------------------------------------------------------------------------
    | The OAuth scopes to request for authentication.
    | Add only the scopes your application needs.
    |
    */
    'scopes' => [
        'https://www.googleapis.com/auth/admin.directory.user',
        'https://www.googleapis.com/auth/admin.directory.group',
        'https://www.googleapis.com/auth/admin.directory.orgunit',
        'https://www.googleapis.com/auth/admin.directory.device.chromeos',
        'https://www.googleapis.com/auth/admin.directory.device.mobile',
    ],

    /*
    |--------------------------------------------------------------------------
    | Protected Resources
    |--------------------------------------------------------------------------
    | List of users and groups that cannot be deleted.
    | This is a safety measure to prevent accidental deletion.
    |
    */
    'undeletable' => [
        'users' => env('GOOGLE_WORKSPACE_UNDELETABLE_USERS', ''),
        'groups' => env('GOOGLE_WORKSPACE_UNDELETABLE_GROUPS', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry Configuration
    |--------------------------------------------------------------------------
    | Configure API request retries for failed requests.
    |
    */
    'retry' => [
        'max_attempts' => env('GOOGLE_WORKSPACE_RETRY_MAX_ATTEMPTS', 3),
        'delay_ms' => env('GOOGLE_WORKSPACE_RETRY_DELAY_MS', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Timeout Configuration
    |--------------------------------------------------------------------------
    | API request timeouts in seconds.
    |
    */
    'timeouts' => [
        'connect' => 10,
        'read' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    | Enable detailed logging for API operations.
    |
    */
    'logging' => [
        'enabled' => env('GOOGLE_WORKSPACE_LOGGING', false),
        'channel' => env('GOOGLE_WORKSPACE_LOG_CHANNEL', 'single'),
    ],
];
