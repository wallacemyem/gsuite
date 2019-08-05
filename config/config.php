<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Account to impersonate
    |--------------------------------------------------------------------------
    | The email of the account to impersonate, account should have neccessary
    | permissions for the scopes requested
    | @link https://developers.google.com/admin-sdk/directory/v1/reference/
    */
    'subject' => env('GOOGLE_SERVICE_ACCOUNT'),

    /*
    |--------------------------------------------------------------------------
    | Path to Credentials
    |--------------------------------------------------------------------------
    | This should be the full path to the credentials file supplied
    | by google when creating a service account. Ensure you add
    | your credentials file to your .gitignore file
    */
    'credentials_path' => storage_path('your-credentials.json'),

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    | The scopes requested
    | @link https://developers.google.com/admin-sdk/directory/v1/reference/
    */
    'scopes' => [
        'https://www.googleapis.com/auth/admin.directory.user',
        'https://www.googleapis.com/auth/admin.directory.group'
    ],

    /*
    |--------------------------------------------------------------------------
    | Domain
    |--------------------------------------------------------------------------
    | Your gsuite domain
    | @link https://developers.google.com/admin-sdk/directory/v1/reference/
    */
    'domain' => 'business.com',
    
    /*
    |--------------------------------------------------------------------------
    | Groups cache name
    |--------------------------------------------------------------------------
    | Name to cache the gsuite groups under
    | @link https://developers.google.com/admin-sdk/directory/v1/reference/
    */
    'group-cache' => 'gsuite:groups',

    /*
    |--------------------------------------------------------------------------
    | Accounts cache name
    |--------------------------------------------------------------------------
    | Name to cache the gsuite accounts under
    | @link https://developers.google.com/admin-sdk/directory/v1/reference/
    */
    'accounts-cache' => 'gsuite:accounts',
    
];
