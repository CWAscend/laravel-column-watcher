<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Enable Column Watcher
    |--------------------------------------------------------------------------
    |
    | When disabled, no watchers will be triggered. Useful for migrations,
    | seeding, or testing scenarios where you want to bypass watchers.
    |
    */
    'enabled' => env('COLUMN_WATCHER_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Default Watcher Namespace
    |--------------------------------------------------------------------------
    |
    | The default namespace where watcher classes will be generated when
    | using the make:watcher artisan command.
    |
    */
    'namespace' => 'App\\Watchers',

    /*
    |--------------------------------------------------------------------------
    | Model Paths
    |--------------------------------------------------------------------------
    |
    | Directories to scan for Eloquent models when running watcher:list.
    | These paths are relative to the base path of your application.
    |
    */
    'model_paths' => [
        'app/Models',
    ],
];
