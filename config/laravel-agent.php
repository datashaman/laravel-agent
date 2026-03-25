<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Task Store Driver
    |--------------------------------------------------------------------------
    |
    | The driver used to persist tasks created by task management tools.
    | Supported: "memory", "cache", "database"
    |
    */

    'task_store' => env('AGENT_TASK_STORE', 'memory'),

    /*
    |--------------------------------------------------------------------------
    | RunCommand Executor
    |--------------------------------------------------------------------------
    |
    | The default execution mode for RunCommand background tasks.
    | Supported: "sync", "async", "queued"
    |
    */

    'executor' => env('AGENT_EXECUTOR', 'async'),

    /*
    |--------------------------------------------------------------------------
    | Cache Task Store Settings
    |--------------------------------------------------------------------------
    */

    'cache' => [
        'store' => env('AGENT_CACHE_STORE'),
        'prefix' => 'laravel-agent:tasks:',
        'ttl' => 3600,
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Task Store Settings
    |--------------------------------------------------------------------------
    */

    'database' => [
        'connection' => env('AGENT_DB_CONNECTION'),
        'table' => 'agent_tasks',
    ],

];
