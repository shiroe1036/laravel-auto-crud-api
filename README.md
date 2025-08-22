# Laravel Auto CRUD Package

A highly flexible and customizable Laravel package that provides automatic CRUD operations with dynamic query building and automatic route generation. Built with extensibility in mind, allowing developers to customize every aspect of the functionality through hooks and configuration.

## Features

- 🚀 **Automatic Route Generation**: Generate CRUD routes automatically based on your models
- 🔍 **Dynamic Query Builder**: Advanced query building with filters, relationships, pagination, and more
- � **Highly Customizable**: Extensible through hooks and configuration
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
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/shiroe1036/laravel-auto-crud-api"
        }
    ],
    "require": {
        "fivotech/laravel-auto-crud": "@dev"
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

### 1. Simple Configuration

In `config/auto-crud.php`:

```php
'models' => [
    App\Models\User::class => [],
    App\Models\Post::class => [
        'exclude_methods' => ['destroy'],
    ],
],
```

Enable auto-generation in `.env`:
```env
AUTO_CRUD_GENERATE_ROUTES=true
```

This automatically generates RESTful routes for your models!

### 2. Using the Generic Query Builder

The package provides a powerful query builder for complex queries:

```php
use FivoTech\LaravelAutoCrud\Services\GenericQueryBuilderService;

$queryBuilder = new GenericQueryBuilderService($request, User::class);
$users = $queryBuilder->getCollections();
$paginatedUsers = $queryBuilder->getCollectionsPaginated();
```

### 3. Extending the Base Controller

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

For each configured model, you get:

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/{resource}` | List all items |
| POST | `/api/{resource}` | Create new item |
| GET | `/api/{resource}/{id}` | Show specific item |
| PUT | `/api/{resource}/{id}` | Update item |
| DELETE | `/api/{resource}/{id}` | Delete item |
| GET | `/api/{resource}/paginate` | Paginated list |
| GET | `/api/{resource}/one` | Single item with query builder |

## Why This Package?

✅ **No Vendor Lock-in**: Hooks allow complete customization without modifying core package
✅ **Incremental Adoption**: Use as much or as little as you need
✅ **Performance**: Optional caching and optimized queries
✅ **Security**: Flexible authorization through hooks
✅ **Maintainability**: Consistent patterns across your application
✅ **Extensibility**: Add any custom logic through the hook system

## Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## License

MIT License. See [LICENSE.md](LICENSE.md) for more information.







