<?php

namespace FivoTech\LaravelAutoCrud\Examples;

use FivoTech\LaravelAutoCrud\Services\GenericQueryBuilderService;
use FivoTech\LaravelAutoCrud\Services\AutoRouteGeneratorService;
use FivoTech\LaravelAutoCrud\Controllers\AutoCrudController;
use Illuminate\Http\Request;

/**
 * Demo class showing how to use the Laravel Auto CRUD package
 *
 * This is for demonstration purposes only
 */
class PackageDemo
{
    /**
     * Demo: Using GenericQueryBuilderService directly
     */
    public function demoQueryBuilder()
    {
        // Create a mock request with query parameters
        $request = new Request([
            'filters' => json_encode([['name', 'like', '%john%'], ['age', '>', 18]]),
            'orFilters' => json_encode([['email', 'like', '%gmail%'], ['email', 'like', '%yahoo%']]),
            'filtersIn' => json_encode(['field' => 'id', 'values' => [1, 2, 3, 4, 5]]),
            'order' => json_encode(['field' => 'created_at', 'order' => 'desc']),
            'relationship' => json_encode([
                ['key' => 'posts'],
                ['key' => 'comments', 'query' => ['filters' => [['approved', '=', true]]]]
            ]),
            'select' => json_encode(['id', 'name', 'email', 'created_at']),
            'per_page' => 15
        ]);

        // Assuming you have a User model
        $userModel = 'App\Models\User';

        $queryBuilder = new GenericQueryBuilderService($request, $userModel);

        // Get all collections with filters applied
        $collections = $queryBuilder->getCollections();

        // Get paginated results
        $paginatedResults = $queryBuilder->getCollectionsPaginated();

        // Get single record
        $singleRecord = $queryBuilder->getOne();

        return [
            'collections' => $collections,
            'paginated' => $paginatedResults,
            'single' => $singleRecord
        ];
    }

    /**
     * Demo: Using AutoCrudController
     */
    public function demoCrudController()
    {
        // Example of how to extend the AutoCrudController
        // In practice, this would be in a separate file:
        // app/Http/Controllers/UserController.php

        /*
        class UserController extends AutoCrudController
        {
            public function __construct()
            {
                // Pass the model and any methods that should skip enseigne verification
                parent::__construct('App\Models\User', ['getOne']);
            }

            // You can override methods to add custom logic
            public function index(Request $request): \Illuminate\Http\JsonResponse
            {
                // Custom logic before
                $customData = $this->customPreProcessing($request);

                // Call parent method
                $result = parent::index($request);

                // Custom logic after
                $this->customPostProcessing($result, $customData);

                return $result;
            }

            private function customPreProcessing(Request $request)
            {
                // Your custom logic here
                return ['timestamp' => now()];
            }

            private function customPostProcessing($result, $customData)
            {
                // Your custom logic here
                \Illuminate\Support\Facades\Log::info('Request processed at: ' . $customData['timestamp']);
            }
        }
        */

        return 'See comment above for controller example';
    }

    /**
     * Demo: Route generation
     */
    public function demoRouteGeneration()
    {
        $routeGenerator = new AutoRouteGeneratorService();

        // Get route information for a model
        $routeInfo = $routeGenerator->getModelRouteInfo('App\Models\User', [
            'middleware' => ['auth:sanctum'],
            'exclude_methods' => ['destroy']
        ]);

        // Scan for models
        $discoveredModels = $routeGenerator->scanForModels(app_path('Models'));

        return [
            'route_info' => $routeInfo,
            'discovered_models' => $discoveredModels
        ];
    }

    /**
     * Demo: Complex query parameters
     */
    public function demoComplexQueries()
    {
        $examples = [
            'basic_filter' => [
                'url' => '/api/users?filters=[["name","like","%john%"]]',
                'description' => 'Filter users where name contains "john"'
            ],

            'multiple_filters' => [
                'url' => '/api/users?filters=[["name","like","%john%"],["age",">",18],["status","=","active"]]',
                'description' => 'Multiple AND conditions'
            ],

            'or_filters' => [
                'url' => '/api/users?orFilters=[["email","like","%gmail%"],["email","like","%yahoo%"]]',
                'description' => 'OR conditions for email domains'
            ],

            'wherein_filter' => [
                'url' => '/api/users?filtersIn={"field":"id","values":[1,2,3,4,5]}',
                'description' => 'Filter by multiple IDs'
            ],

            'multiple_wherein' => [
                'url' => '/api/users?filtersIn=[{"field":"id","values":[1,2,3]},{"field":"role","values":["admin","moderator"]}]',
                'description' => 'Multiple whereIn conditions'
            ],

            'with_relationships' => [
                'url' => '/api/users?relationship=[{"key":"posts"},{"key":"comments","query":{"filters":[["approved","=",true]]}}]',
                'description' => 'Load relationships with conditional loading'
            ],

            'ordering' => [
                'url' => '/api/users?order={"field":"created_at","order":"desc"}',
                'description' => 'Order by creation date descending'
            ],

            'field_selection' => [
                'url' => '/api/users?select=["id","name","email"]',
                'description' => 'Select only specific fields'
            ],

            'pagination' => [
                'url' => '/api/users/paginate?per_page=20&page=2',
                'description' => 'Paginated results with custom page size'
            ],

            'relationship_filters' => [
                'url' => '/api/users?relationshipFilter=[{"relationship":"posts","filters":[["published","=",true]]}]',
                'description' => 'Filter users who have published posts'
            ],

            'complex_combined' => [
                'url' => '/api/users?filters=[["age",">",18]]&orFilters=[["name","like","%admin%"]]&filtersIn={"field":"department_id","values":[1,2,3]}&order={"field":"name","order":"asc"}&relationship=[{"key":"department"}]&select=["id","name","email","department_id"]',
                'description' => 'Complex query combining multiple features'
            ]
        ];

        return $examples;
    }

    /**
     * Demo: Multitenant usage
     */
    public function demoMultitenantUsage()
    {
        $examples = [
            'get_users' => [
                'method' => 'GET',
                'url' => '/api/users?enseigneId=123',
                'description' => 'Get users for specific enseigne'
            ],

            'create_user' => [
                'method' => 'POST',
                'url' => '/api/users',
                'body' => [
                    'enseigneId' => 123,
                    'name' => 'John Doe',
                    'email' => 'john@example.com'
                ],
                'description' => 'Create user with automatic enseigne assignment'
            ],

            'bulk_create' => [
                'method' => 'POST',
                'url' => '/api/users',
                'body' => [
                    'enseigneId' => 123,
                    '0' => ['isUseInsertMode' => true],
                    '1' => ['name' => 'User 1', 'email' => 'user1@example.com'],
                    '2' => ['name' => 'User 2', 'email' => 'user2@example.com'],
                    '3' => ['name' => 'User 3', 'email' => 'user3@example.com']
                ],
                'description' => 'Bulk create users for enseigne'
            ],

            'update_with_relations' => [
                'method' => 'PUT',
                'url' => '/api/users/123',
                'body' => [
                    'enseigneId' => 123,
                    'name' => 'Updated Name',
                    'role_ids' => [1, 2, 3] // Many-to-many relationship
                ],
                'description' => 'Update user with many-to-many relationships'
            ]
        ];

        return $examples;
    }

    /**
     * Demo: Configuration examples
     */
    public function demoConfiguration()
    {
        $configExamples = [
            'basic_model_config' => [
                'App\Models\User::class' => [
                    'middleware' => ['auth:sanctum'],
                ],
            ],

            'custom_controller_config' => [
                'App\Models\User::class' => [
                    'controller' => 'App\Http\Controllers\UserController::class',
                    'middleware' => ['auth:sanctum', 'verified'],
                    'exclude_methods' => ['destroy'],
                ],
            ],

            'custom_routes_config' => [
                'App\Models\Product::class' => [
                    'route_name_prefix' => 'products',
                    'include_methods' => ['index', 'show', 'store'],
                    'middleware' => ['auth:sanctum', 'admin'],
                ],
            ],

            'multitenant_config' => [
                'multitenant' => [
                    'enabled' => true,
                    'enseigne_field' => 'enseigne_id',
                    'user_enseigne_model' => 'App\Models\Multitenant\UsersEnseignes',
                    'exclude_methods' => ['getOne'],
                ],
            ],

            'query_builder_config' => [
                'query_builder' => [
                    'max_per_page' => 100,
                    'default_per_page' => 20,
                    'enable_caching' => true,
                    'cache_ttl' => 1800,
                ],
            ]
        ];

        return $configExamples;
    }
}
