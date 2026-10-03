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
    | The OAuth scopes to request for authentication. The defaults cover the
    | users and groups repositories; add others (see the ApiScope enum) only
    | if your application calls those APIs through services().
    |
    */
    'scopes' => [
        'https://www.googleapis.com/auth/admin.directory.user',
        'https://www.googleapis.com/auth/admin.directory.group',
    ],

    /*
    |--------------------------------------------------------------------------
    | Protected Resources
    |--------------------------------------------------------------------------
    | Comma-separated users and groups that cannot be deleted or renamed
    | (protected users also cannot be suspended). Emails, aliases or IDs.
    |
    */
    'undeletable' => [
        'users' => env('GOOGLE_WORKSPACE_UNDELETABLE_USERS', ''),
        'groups' => env('GOOGLE_WORKSPACE_UNDELETABLE_GROUPS', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Admin Promotion
    |--------------------------------------------------------------------------
    | users()->makeAdmin() grants full super admin rights. It is disabled
    | unless you explicitly opt in here.
    |
    */
    'allow_admin_promotion' => env('GOOGLE_WORKSPACE_ALLOW_ADMIN_PROMOTION', false),

    /*
    |--------------------------------------------------------------------------
    | Retry Configuration
    |--------------------------------------------------------------------------
    | Total attempts (including the first) for requests that fail with a
    | transient error (5xx, rate limit, network). The delay grows exponentially.
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
        'connect' => env('GOOGLE_WORKSPACE_CONNECT_TIMEOUT', 10),
        'read' => env('GOOGLE_WORKSPACE_READ_TIMEOUT', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    | Audit log of every change made through the package (creates, updates,
    | deletes, suspensions, membership and admin changes). Leave channel
    | empty to use your application's default log channel.
    |
    */
    'logging' => [
        'enabled' => env('GOOGLE_WORKSPACE_LOGGING', true),
        'channel' => env('GOOGLE_WORKSPACE_LOG_CHANNEL'),
    ],
];
