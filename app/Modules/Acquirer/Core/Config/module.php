<?php

return [
    'enabled' => env('ACQUIRER_MODULE_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Supported Acquirers
    |--------------------------------------------------------------------------
    | List of acquirer codes recognised by the system. New acquirers must be
    | added here and must provide a parser that implements AcquirerParserInterface.
    */
    'supported_acquirers' => array_filter(
        explode(',', env('ACQUIRER_SUPPORTED', 'ALELO'))
    ),

    /*
    |--------------------------------------------------------------------------
    | File Storage
    |--------------------------------------------------------------------------
    | Disk name (from config/filesystems.php) and path prefix used for
    | storing ingested files. Switch to "s3" in production for shared access.
    */
    'storage' => [
        'disk'   => env('ACQUIRER_STORAGE_DISK', 'local'),
        'prefix' => 'ingestions',
    ],

    /*
    |--------------------------------------------------------------------------
    | Processing
    |--------------------------------------------------------------------------
    */
    'processing' => [
        'batch_size'    => (int) env('ACQUIRER_BATCH_SIZE', 100),
        'max_attempts'  => (int) env('ACQUIRER_MAX_ATTEMPTS', 3),
        'retry_backoff' => (int) env('ACQUIRER_RETRY_BACKOFF', 60),   // seconds
        'timeout'       => (int) env('ACQUIRER_TIMEOUT', 600),        // seconds
        'queue'         => env('ACQUIRER_QUEUE', 'default'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Upload Constraints
    |--------------------------------------------------------------------------
    */
    'upload' => [
        'max_file_size'      => (int) env('ACQUIRER_MAX_FILE_SIZE', 104857600), // 100 MB
        'allowed_extensions' => ['txt'],
    ],

    /*
    |--------------------------------------------------------------------------
    | API Authentication
    |--------------------------------------------------------------------------
    | Bearer token required for API access.  When empty in non-production
    | environments, auth is bypassed for convenience.
    */
    'api' => [
        'token' => env('ACQUIRER_API_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    */
    'pagination' => [
        'per_page'     => (int) env('ACQUIRER_PER_PAGE', 50),
        'max_per_page' => (int) env('ACQUIRER_MAX_PER_PAGE', 200),
    ],
];
