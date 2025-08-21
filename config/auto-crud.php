<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Auto Generate Routes
    |--------------------------------------------------------------------------
    |
    | When enabled, the package will automatically generate CRUD routes
    | for all models defined in the 'models' configuration array.
    |
    */
    'auto_generate_routes' => env('AUTO_CRUD_GENERATE_ROUTES', false),

    /*
    |--------------------------------------------------------------------------
    | Route Prefix
    |--------------------------------------------------------------------------
    |
    | The prefix to be applied to all auto-generated routes.
    |
    */
    'route_prefix' => env('AUTO_CRUD_ROUTE_PREFIX', 'api'),

    /*
    |--------------------------------------------------------------------------
    | Route Middleware
    |--------------------------------------------------------------------------
    |
    | Middleware to be applied to all auto-generated routes.
    |
    */
    'middleware' => [
        'api',
        // 'auth:sanctum', // Uncomment if you need authentication
    ],

    /*
    |--------------------------------------------------------------------------
    | Models Configuration
    |--------------------------------------------------------------------------
    |
    | Define which models should have auto-generated CRUD routes.
    | You can specify the model class and optionally customize the controller.
    |
    */
    'models' => [
        // Example configuration:
        // App\Models\User::class => [
        //     'controller' => App\Http\Controllers\UserController::class,
        //     'exclude_methods' => ['destroy'], // Optional: exclude specific methods
        //     'include_methods' => ['index', 'store', 'show', 'update'], // Optional: only include specific methods
        //     'middleware' => ['auth:sanctum'], // Optional: additional middleware for this model
        //     'route_name_prefix' => 'users', // Optional: custom route name prefix
        //     'hooks' => [ // Optional: custom hooks for this model
        //         'authorization' => function($request, $method, $model) {
        //             // Custom authorization logic
        //             return true;
        //         },
        //         'preprocess_data' => function($data, $request, $operation, $model) {
        //             // Custom data preprocessing
        //             return $data;
        //         },
        //         'postprocess_response' => function($response, $request, $operation, $model) {
        //             // Custom response postprocessing
        //             return $response;
        //         },
        //     ],
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Controller
    |--------------------------------------------------------------------------
    |
    | The default controller to use when no specific controller is defined
    | for a model in the models configuration.
    |
    */
    'default_controller' => FivoTech\LaravelAutoCrud\Controllers\AutoCrudController::class,

    /*
    |--------------------------------------------------------------------------
    | Available CRUD Methods
    |--------------------------------------------------------------------------
    |
    | Define which CRUD methods are available for auto-generation.
    |
    */
    'crud_methods' => [
        'index' => [
            'http_method' => 'GET',
            'route_pattern' => '{resource}',
        ],
        'store' => [
            'http_method' => 'POST',
            'route_pattern' => '{resource}',
        ],
        // Specific routes MUST come before parameterized routes
        'paginateCollection' => [
            'http_method' => 'GET',
            'route_pattern' => '{resource}/paginate',
        ],
        'getOne' => [
            'http_method' => 'GET',
            'route_pattern' => '{resource}/one',
        ],
        // Parameterized routes come last
        'show' => [
            'http_method' => 'GET',
            'route_pattern' => '{resource}/{id}',
            'where' => ['id' => '[0-9]+'], // Add constraint to only match numeric IDs
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
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Naming Convention
    |--------------------------------------------------------------------------
    |
    | Define how route names should be generated.
    | Available placeholders: {resource}, {method}
    |
    */
    'route_name_pattern' => '{resource}.{method}',

    /*
    |--------------------------------------------------------------------------
    | Query Builder Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for the GenericQueryBuilderService.
    |
    */
    'query_builder' => [
        'max_per_page' => 250,
        'default_per_page' => 25,
        'enable_caching' => env('AUTO_CRUD_ENABLE_CACHING', false),
        'cache_ttl' => env('AUTO_CRUD_CACHE_TTL', 3600), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Global Hooks
    |--------------------------------------------------------------------------
    |
    | Define global hooks that will be applied to all models unless overridden.
    |
    */
    'global_hooks' => [
        // 'authorization' => function($request, $method, $model) {
        //     // Global authorization logic
        //     return true;
        // },
        // 'preprocess_data' => function($data, $request, $operation, $model) {
        //     // Global data preprocessing
        //     return $data;
        // },
        // 'postprocess_response' => function($response, $request, $operation, $model) {
        //     // Global response postprocessing
        //     return $response;
        // },
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    |
    | Security-related configuration options.
    |
    */
    'security' => [
        'validate_json_params' => true,
        'max_json_depth' => 10,
        'sanitize_input' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Rules
    |--------------------------------------------------------------------------
    |
    | Define custom validation rules for different operations.
    |
    */
    'validation' => [
        // 'store' => [
        //     // Global validation rules for store operations
        // ],
        // 'update' => [
        //     // Global validation rules for update operations
        // ],
    ],
];
