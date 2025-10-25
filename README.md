# Laravel Auto CRUD Package

A highly flexible and customizable Laravel package that provides automatic CRUD operations with dynamic query building, automatic route generation, and comprehensive route management. Built with extensibility in mind, allowing developers to customize every aspect of the functionality through hooks and configuration.

## Features

- 🚀 **Automatic Route Generation**: Generate CRUD routes automatically based on your models
- � **Route Management**: Track, reset, and manage generated routes with metadata
- 🛡️ **Conflict Prevention**: Prevent route conflicts with existing routes in your application
- �🔍 **Dynamic Query Builder**: Advanced query building with filters, relationships, pagination, and more
- ⚙️ **Highly Customizable**: Extensible through hooks and configuration
- 📝 **JSON Query Parameters**: Support for complex filtering via JSON query parameters
- 🔄 **Relationship Handling**: Automatic handling of many-to-many relationships
- 📄 **Pagination Support**: Built-in pagination with configurable limits
- 🔒 **Flexible Authorization**: Custom authorization through hooks
- 💾 **Caching Support**: Optional query result caching
- 🪝 **Hook System**: Pre and post-processing hooks for complete customization

## Installation

### Via Composer

Add to your `composer.json`:

```json
{
    "require": {
        "fivotech/laravel-auto-crud": "^1.0"
    }
}
```

Then run:
```bash
composer update
```

### Publish Configuration

```bash
php artisan vendor:publish --tag=auto-crud-config
```

## Basic Usage

### 1. Safe Configuration for Existing Projects

For existing Laravel projects with existing routes in `api.php`, use the safe configuration:

```php
// config/auto-crud.php
return [
    // Disabled by default to prevent conflicts
    'auto_generate_routes' => false,

    // Use isolated prefix to avoid conflicts
    'route_prefix' => 'auto-crud',

    // Prevent route conflicts (highly recommended)
    'prevent_route_conflicts' => true,

    // Use isolated namespace for route names
    'route_name_pattern' => 'auto-crud.{resource}.{method}',

    // Auto-reset routes when config changes
    'auto_reset_on_config_change' => true,

    'models' => [
        App\Models\User::class => [],
        App\Models\Post::class => [
            'exclude_methods' => ['destroy'],
        ],
    ],
];
```

### 2. Environment Configuration

```env
# Route generation (disabled by default for safety)
AUTO_CRUD_GENERATE_ROUTES=false

# Use isolated prefix to prevent conflicts
AUTO_CRUD_ROUTE_PREFIX=auto-crud

# Enable conflict prevention
AUTO_CRUD_PREVENT_CONFLICTS=true

# Auto-reset when config changes
AUTO_CRUD_AUTO_RESET=true

# Use isolated route naming
AUTO_CRUD_ROUTE_NAME_PATTERN=auto-crud.{resource}.{method}
```

### 3. Using the Generic Query Builder

The package provides a powerful query builder for complex queries:

```php
use FivoTech\LaravelAutoCrud\Services\GenericQueryBuilderService;

$queryBuilder = new GenericQueryBuilderService($request, User::class);
$users = $queryBuilder->getCollections();
$paginatedUsers = $queryBuilder->getCollectionsPaginated();
```

### 4. Extending the Base Controller

```php
use FivoTech\LaravelAutoCrud\Controllers\AutoCrudController;

class UserController extends AutoCrudController
{
    public function __construct()
    {
        parent::__construct(User::class);
    }

    // All CRUD methods are handled automatically
    // Override specific methods if needed
}
```

## Route Management Commands

The package provides comprehensive route management with tracking and reset capabilities:

### Route Generation Commands

```bash
# Validate routes for conflicts before generating
php artisan auto-crud:generate-routes --validate

# Generate routes with dry-run to preview
php artisan auto-crud:generate-routes --dry-run

# Generate routes with automatic reset of existing ones
php artisan auto-crud:generate-routes --reset

# Generate routes for all configured models
php artisan auto-crud:generate-routes

# Generate for specific model
php artisan auto-crud:generate-routes --model="App\Models\User"

# Scan and generate routes for all discovered models
php artisan auto-crud:generate-routes --scan --directory="app/Models"
```

### Route Reset Commands

```bash
# Show current auto-generated routes
php artisan auto-crud:reset-routes --show

# Reset all auto-generated routes (with confirmation)
php artisan auto-crud:reset-routes --all

# Reset routes for specific models
php artisan auto-crud:reset-routes --models="App\Models\User,App\Models\Post"

# Force reset without confirmation (for scripts)
php artisan auto-crud:reset-routes --all --force

# Clean up stale metadata
php artisan auto-crud:reset-routes --cleanup
```

### Development Workflow

```bash
# 1. Always validate first
php artisan auto-crud:generate-routes --validate

# 2. Preview with dry-run
php artisan auto-crud:generate-routes --dry-run

# 3. Generate with auto-reset for clean state
php artisan auto-crud:generate-routes --reset

# 4. Check what was generated
php artisan auto-crud:reset-routes --show
```

## Route Conflict Prevention

The package includes comprehensive conflict detection to safely work with existing Laravel routes:

### Conflict Detection Features

- **Route Name Conflicts**: Prevents duplicate route names
- **Route Pattern Conflicts**: Detects conflicting URI patterns
- **Parameter Conflicts**: Handles route parameter overlaps
- **HTTP Method Conflicts**: Only checks conflicts for matching methods

### Safe Integration with Existing Projects

```php
// Use isolated prefix to avoid conflicts
'route_prefix' => 'auto-crud', // Routes become /auto-crud/users instead of /api/users

// Use isolated route naming
'route_name_pattern' => 'auto-crud.{resource}.{method}', // Routes named auto-crud.users.index

// Enable conflict prevention
'prevent_route_conflicts' => true, // Skip conflicting routes automatically
```

### Route Metadata Tracking

Each generated route is tracked with metadata:

```php
[
    'route_name' => [
        'model' => 'App\Models\User',
        'method' => 'index',
        'pattern' => 'users',
        'http_method' => 'GET',
        'generated_at' => '2025-08-24T10:30:00Z'
    ]
]
```

### Route Validation Examples

```bash
# Check for potential conflicts
php artisan auto-crud:generate-routes --validate

# Example output:
# ⚠️ Route conflicts detected:
# Model    Method  HTTP  Pattern      Name              Reason
# User     index   GET   api/users    users.index       Route name already exists
# Post     show    GET   api/posts/1  posts.show        Route pattern conflicts
```

## Advanced Customization with Hooks

The package's real power lies in its hook system, allowing complete customization without modifying the core package.

### 1. Authorization Hooks

```php
use FivoTech\LaravelAutoCrud\Controllers\AutoCrudController;

class SecureController extends AutoCrudController
{
    public function __construct($model = null)
    {
        $hooks = [
            'authorization' => function($request, $method, $model) {
                // Custom authorization logic
                if ($method === 'destroy') {
                    return $request->user()?->isAdmin() ?? false;
                }
                return true;
            }
        ];

        parent::__construct($model, ['hooks' => $hooks]);
    }
}
```

### 2. Data Processing Hooks

```php
$hooks = [
    'preprocess_data' => function($data, $request, $operation, $model) {
        // Add user_id to all create operations
        if ($operation === 'store') {
            $data['user_id'] = auth()->id();
        }
        return $data;
    },

    'postprocess_response' => function($response, $request, $operation, $model) {
        // Add metadata to all responses
        if (is_array($response)) {
            return [
                'data' => $response,
                'meta' => ['timestamp' => now()]
            ];
        }
        return $response;
    }
];
```

### 3. Multitenant Implementation Example

```php
use FivoTech\LaravelAutoCrud\Examples\MultitenantController;

class PersonController extends MultitenantController
{
    public function __construct()
    {
        parent::__construct(Person::class);
        // Multitenant functionality is automatically handled!
    }
}
```

### 4. Configuration-Based Hooks

In `config/auto-crud.php`:

```php
'models' => [
    App\Models\User::class => [
        'hooks' => [
            'authorization' => function($request, $method, $model) {
                return $request->user()?->can($method, $model);
            },
            'preprocess_data' => function($data, $request, $operation, $model) {
                // Sanitize input
                if (isset($data['email'])) {
                    $data['email'] = strtolower(trim($data['email']));
                }
                return $data;
            }
        ]
    ],
],

// Global hooks applied to all models
'global_hooks' => [
    'postprocess_response' => function($response, $request, $operation, $model) {
        // Log all operations
        \Log::info("CRUD operation: {$operation} on " . (is_string($model) ? $model : get_class($model)));
        return $response;
    }
]
```

## Available Hooks

| Hook Name | Parameters | Description |
|-----------|------------|-------------|
| `authorization` | `($request, $method, $model)` | Control access to operations |
| `preprocess_data` | `($data, $request, $operation, $model)` | Modify data before operations |
| `preprocess_bulk_data` | `($dataArray, $request, $model)` | Modify bulk data for inserts |
| `preprocess_single_data` | `($data, $request, $model)` | Modify single item data |
| `postprocess_response` | `($response, $request, $operation, $model)` | Modify response data |
| `before_delete` | `($model, $request)` | Execute before deletion |

## Query Parameters

The package supports powerful query parameters:

**Filters:**
```
GET /api/users?filters=[["name","like","%john%"],["age",">",18]]
```

**OR Filters:**
```
GET /api/users?orFilters=[["email","like","%gmail%"],["email","like","%yahoo%"]]
```

**Relationships:**
```
GET /api/users?relationship=[{"key":"posts","query":{"filters":[["published","=",true]]}}]
```

**Pagination:**
```
GET /api/users/paginate?per_page=20&page=2
```

**Field Selection:**
```
GET /api/users?select=["id","name","email"]
```

## Example Implementations

### 1. Audit Trail Controller

```php
class AuditController extends AutoCrudController
{
    public function __construct($model = null)
    {
        $auditHooks = [
            'preprocess_data' => [$this, 'addAuditFields'],
            'before_delete' => [$this, 'logDeletion'],
        ];

        parent::__construct($model, ['hooks' => $auditHooks]);
    }

    public function addAuditFields($data, $request, $operation, $model)
    {
        if ($operation === 'store') {
            $data['created_by'] = auth()->id();
        } elseif ($operation === 'update') {
            $data['updated_by'] = auth()->id();
        }
        return $data;
    }

    public function logDeletion($model, $request)
    {
        \Log::info('Deleted: ' . get_class($model) . ' ID: ' . $model->id);
    }
}
```

### 2. Validation Controller

```php
class ValidatedController extends AutoCrudController
{
    public function __construct($model = null)
    {
        $validationHooks = [
            'preprocess_data' => [$this, 'validateData'],
        ];

        parent::__construct($model, ['hooks' => $validationHooks]);
    }

    public function validateData($data, $request, $operation, $model)
    {
        $rules = $this->getValidationRules($operation, $model);

        $validator = \Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        return $data;
    }

    protected function getValidationRules($operation, $model)
    {
        // Define your validation rules based on operation and model
        return [];
    }
}
```

## Commands

Generate routes for your models:

```bash
# Generate routes for all configured models
php artisan auto-crud:generate-routes

# Scan and generate routes for all models
php artisan auto-crud:generate-routes --scan

# Generate for specific model
php artisan auto-crud:generate-routes --model="App\Models\User"

# Dry run to see what would be generated
php artisan auto-crud:generate-routes --scan --dry-run
```

## Generated API Endpoints

For each configured model, you get these endpoints (with configurable prefix):

| Method | Endpoint | Description | Route Name |
|--------|----------|-------------|------------|
| GET | `/auto-crud/{resource}` | List all items | `auto-crud.{resource}.index` |
| POST | `/auto-crud/{resource}` | Create new item | `auto-crud.{resource}.store` |
| GET | `/auto-crud/{resource}/{id}` | Show specific item | `auto-crud.{resource}.show` |
| PUT | `/auto-crud/{resource}/{id}` | Update item | `auto-crud.{resource}.update` |
| DELETE | `/auto-crud/{resource}/{id}` | Delete item | `auto-crud.{resource}.destroy` |
| GET | `/auto-crud/{resource}/paginate` | Paginated list | `auto-crud.{resource}.paginate` |

### Route Examples

With default isolated configuration:
```
GET    /auto-crud/users           -> auto-crud.users.index
POST   /auto-crud/users           -> auto-crud.users.store
GET    /auto-crud/users/1         -> auto-crud.users.show
PUT    /auto-crud/users/1         -> auto-crud.users.update
DELETE /auto-crud/users/1         -> auto-crud.users.destroy
GET    /auto-crud/users/paginate  -> auto-crud.users.paginate
```

### Customizing Route Patterns

```php
// config/auto-crud.php
'route_prefix' => 'api/v2',           // Changes prefix to /api/v2/
'route_name_pattern' => 'api.{resource}.{method}', // Changes names to api.users.index
```

## Migration Guide for Existing Projects

### Step 1: Install Safely
```bash
composer require fivotech/laravel-auto-crud
php artisan vendor:publish --tag=auto-crud-config
```

### Step 2: Use Safe Configuration
```php
// config/auto-crud.php - Safe defaults
return [
    'auto_generate_routes' => false,        // Disabled by default
    'route_prefix' => 'auto-crud',          // Isolated prefix
    'prevent_route_conflicts' => true,      // Enable conflict detection
    'route_name_pattern' => 'auto-crud.{resource}.{method}',
    'models' => [
        // Add your models here
    ],
];
```

### Step 3: Validate Before Enabling
```bash
php artisan auto-crud:generate-routes --validate
```

### Step 4: Test with Dry Run
```bash
php artisan auto-crud:generate-routes --dry-run
```

### Step 5: Enable Gradually
```php
'auto_generate_routes' => true, // Enable when ready
```

## Troubleshooting

### Common Issues

**Route Conflicts:**
```bash
# Check for conflicts
php artisan auto-crud:generate-routes --validate

# Use isolated prefix
AUTO_CRUD_ROUTE_PREFIX=auto-crud

# Use isolated naming
AUTO_CRUD_ROUTE_NAME_PATTERN=auto-crud.{resource}.{method}
```

**Stale Routes:**
```bash
# Clean up old routes
php artisan auto-crud:reset-routes --cleanup

# Reset all and regenerate
php artisan auto-crud:reset-routes --all
php artisan auto-crud:generate-routes
```

**Route Not Working:**
```bash
# Check current routes
php artisan auto-crud:reset-routes --show

# Validate metadata consistency
php artisan auto-crud:reset-routes --cleanup
```

## Why This Package?

✅ **No Vendor Lock-in**: Hooks allow complete customization without modifying core package
✅ **Incremental Adoption**: Use as much or as little as you need
✅ **Performance**: Optional caching and optimized queries
✅ **Security**: Flexible authorization through hooks
✅ **Maintainability**: Consistent patterns across your application
✅ **Extensibility**: Add any custom logic through the hook system
✅ **Safe Integration**: Conflict prevention ensures compatibility with existing routes
✅ **Route Management**: Track, reset, and manage generated routes comprehensively
✅ **Production Ready**: Enterprise-level route management with metadata tracking

## Advanced Features

### Route Metadata API

```php
use FivoTech\LaravelAutoCrud\Services\AutoRouteGeneratorService;

$routeGenerator = app(AutoRouteGeneratorService::class);

// Check if routes are generated
$hasRoutes = $routeGenerator->hasGeneratedRoutes();

// Get all route metadata
$metadata = $routeGenerator->getGeneratedRoutesMetadata();

// Get routes for specific models
$userRoutes = $routeGenerator->getModelRoutesMetadata(['App\Models\User']);

// Get route counts by model
$counts = $routeGenerator->getGeneratedRoutesCount();

// Validate route consistency
$issues = $routeGenerator->validateGeneratedRoutes();

// Clean up stale metadata
$cleaned = $routeGenerator->cleanupStaleMetadata();
```

### Automated Deployment Workflow

```bash
#!/bin/bash
# deployment-script.sh

# Reset all routes for clean state
php artisan auto-crud:reset-routes --all --force

# Validate configuration
php artisan auto-crud:generate-routes --validate

# Generate routes if validation passes
if [ $? -eq 0 ]; then
    php artisan auto-crud:generate-routes
    echo "Routes generated successfully"
else
    echo "Route conflicts detected, manual review required"
    exit 1
fi

# Clean up any stale metadata
php artisan auto-crud:reset-routes --cleanup
```

### Configuration Templates

**For New Projects:**
```php
// config/auto-crud.php - New project configuration
return [
    'auto_generate_routes' => true,
    'route_prefix' => 'api',
    'prevent_route_conflicts' => true,
    'route_name_pattern' => '{resource}.{method}',
    // ... other settings
];
```

**For Existing Projects:**
```php
// config/auto-crud.php - Existing project configuration
return [
    'auto_generate_routes' => false,         // Manual control
    'route_prefix' => 'auto-crud',           // Isolated prefix
    'prevent_route_conflicts' => true,       // Mandatory for existing projects
    'route_name_pattern' => 'auto-crud.{resource}.{method}', // Isolated naming
    // ... other settings
];
```

## Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## License

MIT License. See [LICENSE.md](LICENSE.md) for more information.







