<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Horizon Domain
    |--------------------------------------------------------------------------
    */
    'domain' => env('HORIZON_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Path
    |--------------------------------------------------------------------------
    */
    'path' => env('HORIZON_PATH', 'horizon'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Connection
    |--------------------------------------------------------------------------
    */
    'use' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Prefix
    |--------------------------------------------------------------------------
    */
    'prefix' => env('HORIZON_PREFIX', 'federation_horizon:'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Route Middleware
    |--------------------------------------------------------------------------
    */
    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Queue Wait Time Thresholds
    |--------------------------------------------------------------------------
    | Values in seconds. Horizon will alert if a queue's wait time exceeds
    | the threshold defined here.
    */
    'waits' => [
        'redis:high'    => 60,
        'redis:default' => 60,
        'redis:low'     => 120,
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Trimming Times
    |--------------------------------------------------------------------------
    | How many minutes Horizon should keep recent and failed jobs.
    */
    'trim' => [
        'recent'        => 60,
        'pending'       => 60,
        'completed'     => 60,
        'recent_failed' => 10080,  // 7 days
        'failed'        => 10080,
        'monitored'     => 10080,
    ],

    /*
    |--------------------------------------------------------------------------
    | Silenced Jobs
    |--------------------------------------------------------------------------
    | These jobs will not be recorded in Horizon's job history.
    */
    'silenced' => [
        // App\Jobs\ExampleJob::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrics
    |--------------------------------------------------------------------------
    */
    'metrics' => [
        'trim_snapshots' => [
            'job'   => 24,
            'queue' => 24,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fast Termination
    |--------------------------------------------------------------------------
    */
    'fast_termination' => false,

    /*
    |--------------------------------------------------------------------------
    | Memory Limit (MB)
    |--------------------------------------------------------------------------
    */
    'memory_limit' => 64,

    /*
    |--------------------------------------------------------------------------
    | Queue Worker Configuration
    |--------------------------------------------------------------------------
    |
    | Three priority levels — new jobs should use one of these names only:
    |   high    — AutoGenerateMetadataJob, SyncEduGainMetadataJob
    |   default — GenerateMetadataJob, CleanupJob, general background work
    |   low     — ValidateEntityMetadataJob, CheckCertificateExpiryJob,
    |             DeliverWebhookJob, SendFederationMailJob
    */
    'environments' => [

        'production' => [

            'supervisor-high' => [
                'connection' => 'redis',
                'queue'      => ['high'],
                'balance'    => 'auto',
                'processes'  => 2,
                'tries'      => 3,
                'timeout'    => 120,
                'memory'     => 128,
            ],

            'supervisor-default' => [
                'connection' => 'redis',
                'queue'      => ['default'],
                'balance'    => 'simple',
                'processes'  => 1,
                'tries'      => 3,
                'timeout'    => 60,
                'memory'     => 64,
            ],

            'supervisor-low' => [
                'connection' => 'redis',
                'queue'      => ['low'],
                'balance'    => 'simple',
                'processes'  => 1,
                'tries'      => 3,
                'timeout'    => 300,
                'memory'     => 128,
            ],
        ],

        'local' => [

            'supervisor-high' => [
                'connection' => 'redis',
                'queue'      => ['high'],
                'balance'    => 'simple',
                'processes'  => 2,
                'tries'      => 3,
                'timeout'    => 120,
                'memory'     => 128,
            ],

            'supervisor-default' => [
                'connection' => 'redis',
                'queue'      => ['default'],
                'balance'    => 'simple',
                'processes'  => 1,
                'tries'      => 3,
                'timeout'    => 60,
                'memory'     => 64,
            ],

            'supervisor-low' => [
                'connection' => 'redis',
                'queue'      => ['low'],
                'balance'    => 'simple',
                'processes'  => 1,
                'tries'      => 3,
                'timeout'    => 300,
                'memory'     => 128,
            ],
        ],

    ],

];
