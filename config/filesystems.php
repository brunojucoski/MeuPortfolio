<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application. Just store away!
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Here you may configure as many filesystem "disks" as you wish, and you
    | may even configure multiple disks of the same driver. Defaults have
    | been set up for each driver as an example of the required values.
    |
    | Supported Drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'throw' => false,
        ],

        'public' => [
            'driver' => env('PUBLIC_STORAGE_DRIVER', 'local'),
            'root' => env('PUBLIC_STORAGE_DRIVER', 'local') === 's3' ? '' : storage_path('app/public'),
            'url' => env('PUBLIC_STORAGE_URL', env('PUBLIC_STORAGE_DRIVER', 'local') === 's3' ? null : env('APP_URL').'/storage'),
            'key' => env('STORAGE_S3_KEY'),
            'secret' => env('STORAGE_S3_SECRET'),
            'region' => env('STORAGE_S3_REGION', 'us-east-1'),
            'bucket' => env('PUBLIC_STORAGE_BUCKET'),
            'endpoint' => env('STORAGE_S3_ENDPOINT'),
            'use_path_style_endpoint' => true,
            'request_checksum_calculation' => 'when_required',
            'response_checksum_validation' => 'when_required',
            'stream_reads' => true,
            'throw' => true,
        ],

        'private' => [
            'driver' => env('PRIVATE_STORAGE_DRIVER', 'local'),
            'root' => env('PRIVATE_STORAGE_DRIVER', 'local') === 's3' ? '' : storage_path('app/private'),
            'key' => env('STORAGE_S3_KEY'),
            'secret' => env('STORAGE_S3_SECRET'),
            'region' => env('STORAGE_S3_REGION', 'us-east-1'),
            'bucket' => env('PRIVATE_STORAGE_BUCKET'),
            'endpoint' => env('STORAGE_S3_ENDPOINT'),
            'use_path_style_endpoint' => true,
            'request_checksum_calculation' => 'when_required',
            'response_checksum_validation' => 'when_required',
            'stream_reads' => true,
            // Supabase bucket privacy is configured in its dashboard, not with S3 ACLs.
            'throw' => true,
        ],

        'legacy_public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'throw' => true,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
