<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SAFE CONFIGURATION FOR EXISTING LARAVEL PROJECTS
    |--------------------------------------------------------------------------
    |
    | This configuration prevents conflicts with existing routes in api.php
    | by using isolated prefixes and namespaces.
    |
    */

    // Disabled by default to prevent conflicts
    'auto_generate_routes' => false,

    // Use isolated prefix to avoid conflicts with existing /api routes
    'route_prefix' => 'auto-crud',

    // Prevent route conflicts (highly recommended)
    'prevent_route_conflicts' => true,

    // Use isolated namespace for route names
    'route_name_pattern' => 'auto-crud.{resource}.{method}',

    // Optional: Use controller namespace to isolate routes
    'route_namespace' => 'AutoCrud',

    // Safe middleware configuration
    'middleware' => [
        'api',
        // Add your authentication middleware here if needed
        // 'auth:sanctum',
    ],

    // Models configuration - add your models here
    'models' => [
        // Example:
        // App\Models\Post::class => [
        //     'controller' => App\Http\Controllers\PostController::class,
        //     'exclude_methods' => ['destroy'], // Exclude dangerous operations
        //     'middleware' => ['auth:sanctum'], // Add authentication
        // ],
    ],

    'default_controller' => FivoTech\LaravelAutoCrud\Controllers\AutoCrudController::class,

    'crud_methods' => [
        'index' => [
            'http_method' => 'GET',
            'route_pattern' => '{resource}',
        ],
        'store' => [
            'http_method' => 'POST',
            'route_pattern' => '{resource}',
        ],
        'show' => [
            'http_method' => 'GET',
            'route_pattern' => '{resource}/{id}',
            'where' => ['id' => '[0-9]+'],
        ],
        'update' => [
            'http_method' => 'PUT',
            'route_pattern' => '{resource}/{id}',
            'where' => ['id' => '[0-9]+'],
        ],
        'destroy' => [
            'http_method' => 'DELETE',
            'route_pattern' => '{resource}/{id}',
            'where' => ['id' => '[0-9]+'],
        ],
        'paginate' => [
            'http_method' => 'GET',
            'route_pattern' => '{resource}/paginate',
        ],
    ],

    // Disable global hooks by default
    'global_hooks' => [],
];
