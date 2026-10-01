<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Passport Guard
    |--------------------------------------------------------------------------
    |
    | Here you may specify which authentication guard Passport will use when
    | authenticating users. This value should correspond with one of your
    | guards that is already present in your "auth" configuration file.
    |
    */

    'guard' => 'web',

    /*
    |--------------------------------------------------------------------------
    | Encryption Keys
    |--------------------------------------------------------------------------
    |
    | Passport uses encryption keys while generating secure access tokens for
    | your application. By default, the keys are stored as local files but
    | can be set via environment variables when that is more convenient.
    |
    */

    // Use forward slashes for file:// URLs so Windows paths work with CryptKey
    'private_key' => env('PASSPORT_PRIVATE_KEY') ?: (function () {
        $path = storage_path('oauth-private.key');
        return str_replace('\\', '/', $path);
    })(),
    'public_key' => env('PASSPORT_PUBLIC_KEY') ?: (function () {
        $path = storage_path('oauth-public.key');
        return str_replace('\\', '/', $path);
    })(),

    /*
    |--------------------------------------------------------------------------
    | Passport Database Connection
    |--------------------------------------------------------------------------
    |
    | By default, Passport's models will utilize your application's default
    | database connection. If you wish to use a different connection you
    | may specify the configured name of the database connection here.
    |
    */

    'connection' => env('PASSPORT_CONNECTION'),

];
