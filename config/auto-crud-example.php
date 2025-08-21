<?php

/*
|--------------------------------------------------------------------------
| Example configuration for Laravel Auto CRUD Package
|--------------------------------------------------------------------------
|
| This file shows how to configure the package with your existing models.
| Copy the relevant parts to your config/auto-crud.php file.
|
*/

return [
    'auto_generate_routes' => env('AUTO_CRUD_GENERATE_ROUTES', false),
    'route_prefix' => env('AUTO_CRUD_ROUTE_PREFIX', 'api'),
    'middleware' => [
        'api',
        'auth:sanctum', // Uncomment if using authentication
    ],

    'models' => [
        // Example configuration for your existing models
        // Uncomment and modify as needed

        /*
        App\Models\User::class => [
            'controller' => App\Http\Controllers\UserController::class,
            'middleware' => ['auth:sanctum'],
            'exclude_methods' => [], // Don't exclude any methods
        ],

        App\Models\Person::class => [
            'controller' => App\Http\Controllers\PersonController::class,
            'middleware' => ['auth:sanctum'],
        ],

        App\Models\Student::class => [
            'controller' => App\Http\Controllers\StudentController::class,
            'middleware' => ['auth:sanctum'],
        ],

        App\Models\Employee::class => [
            'controller' => App\Http\Controllers\EmployeeController::class,
            'middleware' => ['auth:sanctum'],
        ],

        App\Models\Level::class => [
            'controller' => App\Http\Controllers\LevelController::class,
            'middleware' => ['auth:sanctum'],
        ],

        App\Models\Subject::class => [
            'controller' => App\Http\Controllers\SubjectController::class,
            'middleware' => ['auth:sanctum'],
        ],

        App\Models\NoteStudent::class => [
            'controller' => App\Http\Controllers\NoteStudentController::class,
            'middleware' => ['auth:sanctum'],
        ],

        // For models that don't have specific controllers,
        // the package will use the default AutoCrudController
        App\Models\Book::class => [
            'middleware' => ['auth:sanctum'],
        ],
        */],

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
        ],
        'update' => [
            'http_method' => 'PUT',
            'route_pattern' => '{resource}/{id}',
        ],
        'destroy' => [
            'http_method' => 'DELETE',
            'route_pattern' => '{resource}/{id}',
        ],
        'paginateCollection' => [
            'http_method' => 'GET',
            'route_pattern' => '{resource}/paginate',
        ],
        'getOne' => [
            'http_method' => 'GET',
            'route_pattern' => '{resource}/one',
        ],
    ],

    'route_name_pattern' => '{resource}.{method}',

    'query_builder' => [
        'max_per_page' => 250,
        'default_per_page' => 25,
        'enable_caching' => env('AUTO_CRUD_ENABLE_CACHING', false), // Disabled by default
        'cache_ttl' => env('AUTO_CRUD_CACHE_TTL', 3600),
    ],

    'multitenant' => [
        'enabled' => env('AUTO_CRUD_MULTITENANT_ENABLED', true), // Enable for your project
        'enseigne_field' => 'enseigne_id',
        'user_enseigne_model' => 'App\Models\Multitenant\UsersEnseignes',
        'cache_prefix' => 'user_enseigne_',
        'exclude_methods' => ['getOne'], // Methods that don't require enseigne verification
    ],

    'security' => [
        'validate_json_params' => true,
        'max_json_depth' => 10,
        'sanitize_input' => true,
    ],
];
