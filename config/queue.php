<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Queue Connection Name
    |--------------------------------------------------------------------------
    |
    | Laravel's queue API supports an assortment of back-ends via a single
    | API, giving you convenient access to each back-end using the same
    | syntax for every one. Here you may define a default connection.
    |
    */

    'default' => env('QUEUE_CONNECTION', 'redis'),

    /*
    |--------------------------------------------------------------------------
    | Queue Connections
    |--------------------------------------------------------------------------
    |
    | Here you may configure the connection information for each server that
    | is used by your application. A default configuration has been added
    | for each back-end shipped with Laravel. You are free to add more.
    |
    */

    'connections' => [

        'sync' => [
            'driver' => 'sync',
        ],

        'database' => [
            'driver' => 'database',
            'table' => 'jobs',
            'queue' => 'default',
            'retry_after' => 90,
            'after_commit' => false,
        ],

        'beanstalkd' => [
            'driver' => 'beanstalkd',
            'host' => 'localhost',
            'queue' => 'default',
            'retry_after' => 90,
            'block_for' => 0,
            'after_commit' => false,
        ],

        'sqs' => [
            'driver' => 'sqs',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'prefix' => env('SQS_PREFIX', 'https://sqs.us-east-1.amazonaws.com/your-account-id'),
            'queue' => env('SQS_QUEUE', 'default'),
            'suffix' => env('SQS_SUFFIX'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'after_commit' => false,
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => 90,
            'block_for' => null,
            'after_commit' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Job Batching
    |--------------------------------------------------------------------------
    |
    | The following configuration options configure the database and table
    | that store job batching information. These configuration options
    | provide the batching system the details it needs to keep track of
    | job progress, failed jobs, and retain batches for historical reference.
    |
    */

    'batching' => [
        'database' => env('DB_CONNECTION', 'sqlite'),
        'table' => 'job_batches',
    ],

    /*
    |--------------------------------------------------------------------------
    | Failed Queue Jobs
    |--------------------------------------------------------------------------
    |
    | These configuration options configure the behavior of failed queue
    | job logging so you can control which database and table are used
    | to store the jobs that have failed. You may change them to any
    | database / table you wish.
    |
    */

    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('DB_CONNECTION', 'sqlite'),
        'table' => 'failed_jobs',
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Worker Supervisor Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the queue worker supervisors for auto-scaling and monitoring
    |
    */

    'supervisor' => [
        'default' => [
            'min_workers' => env('QUEUE_SUPERVISOR_DEFAULT_MIN_WORKERS', 2),
            'max_workers' => env('QUEUE_SUPERVISOR_DEFAULT_MAX_WORKERS', 10),
            'scale_up_threshold' => env('QUEUE_SUPERVISOR_DEFAULT_SCALE_UP', 50),
            'scale_down_threshold' => env('QUEUE_SUPERVISOR_DEFAULT_SCALE_DOWN', 10),
        ],

        'high-priority' => [
            'min_workers' => env('QUEUE_SUPERVISOR_HIGH_MIN_WORKERS', 3),
            'max_workers' => env('QUEUE_SUPERVISOR_HIGH_MAX_WORKERS', 15),
            'scale_up_threshold' => env('QUEUE_SUPERVISOR_HIGH_SCALE_UP', 25),
            'scale_down_threshold' => env('QUEUE_SUPERVISOR_HIGH_SCALE_DOWN', 5),
        ],

        'low-priority' => [
            'min_workers' => env('QUEUE_SUPERVISOR_LOW_MIN_WORKERS', 1),
            'max_workers' => env('QUEUE_SUPERVISOR_LOW_MAX_WORKERS', 5),
            'scale_up_threshold' => env('QUEUE_SUPERVISOR_LOW_SCALE_UP', 100),
            'scale_down_threshold' => env('QUEUE_SUPERVISOR_LOW_SCALE_DOWN', 20),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Monitoring Configuration
    |--------------------------------------------------------------------------
    |
    | Configure queue monitoring and alerting settings
    |
    */

    'monitoring' => [
        'enabled' => env('QUEUE_MONITORING_ENABLED', true),
        'metrics_retention_days' => env('QUEUE_METRICS_RETENTION_DAYS', 30),
        'alert_thresholds' => [
            'pending_jobs' => env('QUEUE_ALERT_PENDING_JOBS', 1000),
            'failed_jobs' => env('QUEUE_ALERT_FAILED_JOBS', 100),
            'processing_time' => env('QUEUE_ALERT_PROCESSING_TIME', 300), // seconds
        ],
        'notification_channels' => ['mail', 'slack'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configure rate limiting for queue operations
    |
    */

    'rate_limiting' => [
        'enabled' => env('QUEUE_RATE_LIMITING_ENABLED', true),
        'max_jobs_per_minute' => env('QUEUE_MAX_JOBS_PER_MINUTE', 1000),
        'max_concurrent_jobs' => env('QUEUE_MAX_CONCURRENT_JOBS', 100),
        'throttle_by_user' => env('QUEUE_THROTTLE_BY_USER', true),
        'user_max_jobs_per_minute' => env('QUEUE_USER_MAX_JOBS_PER_MINUTE', 100),
    ],

];
