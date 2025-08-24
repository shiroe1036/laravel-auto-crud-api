# Installation Guide

## Quick Start for New Projects

### 1. Install via Composer

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

### 2. Publish Configuration
```bash
php artisan vendor:publish --tag=auto-crud-config
```

### 3. Configure Models (New Projects)
```php
// config/auto-crud.php
return [
    'auto_generate_routes' => true,
    'route_prefix' => 'api',
    'prevent_route_conflicts' => true,
    'models' => [
        App\Models\User::class => [],
        App\Models\Post::class => [
            'exclude_methods' => ['destroy'],
        ],
    ],
];
```

### 4. Generate Routes
```bash
php artisan auto-crud:generate-routes
```

## Safe Installation for Existing Projects

⚠️ **Important**: For existing Laravel projects with routes in `api.php`, follow these steps to prevent conflicts.

### 1. Install Package
```bash
composer require fivotech/laravel-auto-crud
php artisan vendor:publish --tag=auto-crud-config
```

### 2. Use Safe Configuration
The package ships with safe defaults. Verify your config:

```php
// config/auto-crud.php
return [
    // SAFE: Disabled by default
    'auto_generate_routes' => false,

    // SAFE: Isolated prefix prevents conflicts
    'route_prefix' => 'auto-crud',

    // SAFE: Conflict detection enabled
    'prevent_route_conflicts' => true,

    // SAFE: Isolated route naming
    'route_name_pattern' => 'auto-crud.{resource}.{method}',

    // SAFE: Auto-reset when config changes
    'auto_reset_on_config_change' => true,

    'models' => [
        // Add your models here safely
    ],
];
```

### 3. Environment Configuration
```env
# Keep disabled initially
AUTO_CRUD_GENERATE_ROUTES=false

# Use isolated prefix
AUTO_CRUD_ROUTE_PREFIX=auto-crud

# Enable safety features
AUTO_CRUD_PREVENT_CONFLICTS=true
AUTO_CRUD_AUTO_RESET=true

# Use isolated naming
AUTO_CRUD_ROUTE_NAME_PATTERN=auto-crud.{resource}.{method}
```

### 4. Validate Before Enabling
```bash
# Check for potential conflicts
php artisan auto-crud:generate-routes --validate

# Preview what would be generated
php artisan auto-crud:generate-routes --dry-run
```

### 5. Add Models Gradually
```php
// config/auto-crud.php
'models' => [
    App\Models\User::class => [
        'exclude_methods' => ['destroy'], // Exclude dangerous operations
        'middleware' => ['auth:sanctum'],  // Add authentication
    ],
],
```

### 6. Test and Enable
```bash
# Test with specific model
php artisan auto-crud:generate-routes --model="App\Models\User" --dry-run

# Generate when ready
php artisan auto-crud:generate-routes

# Enable auto-generation when confident
# Set AUTO_CRUD_GENERATE_ROUTES=true in .env
```

## Verification Steps

### Check Generated Routes
```bash
# View auto-generated routes
php artisan auto-crud:reset-routes --show

# View all Laravel routes
php artisan route:list --name=auto-crud
```

### Test Route Endpoints
With isolated configuration, your routes will be:
```
GET    /auto-crud/users           (instead of /api/users)
POST   /auto-crud/users
GET    /auto-crud/users/1
PUT    /auto-crud/users/1
DELETE /auto-crud/users/1
GET    /auto-crud/users/paginate
```

## Troubleshooting Installation

### Common Issues

**Route Conflicts:**
```bash
# Solution: Use isolated configuration
AUTO_CRUD_ROUTE_PREFIX=auto-crud
AUTO_CRUD_ROUTE_NAME_PATTERN=auto-crud.{resource}.{method}
```

**Package Not Found:**
```bash
# Solution: Check repository configuration
composer update --verbose
```

**Configuration Not Published:**
```bash
# Solution: Force republish
php artisan vendor:publish --tag=auto-crud-config --force
```

**Routes Not Working:**
```bash
# Solution: Check generation status
php artisan auto-crud:reset-routes --show

# Regenerate if needed
php artisan auto-crud:generate-routes --reset
```

## Migration from Manual Routes

If you have existing manual CRUD routes:

### 1. Document Existing Routes
```bash
php artisan route:list > existing_routes.txt
```

### 2. Use Isolated Configuration
```php
'route_prefix' => 'auto-crud',  // Keeps existing /api routes intact
```

### 3. Migrate Gradually
```php
// Start with one model
'models' => [
    App\Models\User::class => [],
],

// Add more models over time
```

### 4. Update Frontend Calls
Change API calls from:
```javascript
// Old
fetch('/api/users')

// New (with isolated prefix)
fetch('/auto-crud/users')
```

## Production Deployment

### Environment Setup
```env
# Production environment
AUTO_CRUD_GENERATE_ROUTES=true
AUTO_CRUD_ROUTE_PREFIX=auto-crud
AUTO_CRUD_PREVENT_CONFLICTS=true
AUTO_CRUD_AUTO_RESET=false  # Disable auto-reset in production
```

### Deployment Script
```bash
#!/bin/bash
# Reset routes for clean state
php artisan auto-crud:reset-routes --all --force

# Validate configuration
php artisan auto-crud:generate-routes --validate

# Generate routes if validation passes
if [ $? -eq 0 ]; then
    php artisan auto-crud:generate-routes
    echo "✅ Routes generated successfully"
else
    echo "❌ Route conflicts detected"
    exit 1
fi

# Clean up metadata
php artisan auto-crud:reset-routes --cleanup
```

## Next Steps

After installation:

1. **Read the Documentation**: Check `README.md` for comprehensive usage
2. **Review Commands**: See `COMMANDS_REFERENCE.md` for command details
3. **Understand Route Management**: Read `ROUTE_MANAGEMENT.md`
4. **Check Conflict Prevention**: Review `ROUTE_CONFLICT_PREVENTION.md`
5. **Explore Examples**: Look at files in the `examples/` directory

## Support

If you encounter issues:
1. Check the troubleshooting section above
2. Validate your configuration with `--validate`
3. Use `--dry-run` to preview changes
4. Check the documentation files for detailed explanations

## Step 1: Install the Package

Add the package to your Laravel project's composer.json:

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

## Step 2: Publish Configuration

```bash
php artisan vendor:publish --tag=auto-crud-config
```

This will publish the configuration file to `config/auto-crud.php`.

## Step 3: Configure Your Models

Edit `config/auto-crud.php` and configure your models:

```php
'models' => [
    App\Models\User::class => [
        'controller' => App\Http\Controllers\UserController::class,
        'middleware' => ['auth:sanctum'],
    ],

    App\Models\Person::class => [
        'controller' => App\Http\Controllers\PersonController::class,
        'middleware' => ['auth:sanctum'],
    ],

    // Add more models as needed
],
```

## Step 4: Update Your Existing Controllers

You can either:

### Option A: Extend the AutoCrudController

```php
<?php

namespace App\Http\Controllers;

use FivoTech\LaravelAutoCrud\Controllers\AutoCrudController;
use App\Models\Person;

class PersonController extends AutoCrudController
{
    public function __construct()
    {
        parent::__construct(Person::class, ['getOne']); // Exclude 'getOne' from enseigne verification
    }

    // You can override methods as needed
    public function index(Request $request): JsonResponse
    {
        // Custom logic before
        $result = parent::index($request);
        // Custom logic after
        return $result;
    }
}
```

### Option B: Use the GenericQueryBuilderService in Your Controllers

```php
<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use FivoTech\LaravelAutoCrud\Services\GenericQueryBuilderService;
use App\Models\Person;
use Illuminate\Http\Request;

class PersonController extends Controller
{
    public function index(Request $request)
    {
        $queryBuilder = new GenericQueryBuilderService($request, Person::class);
        $result = $queryBuilder->getCollections();
        return response()->json($result);
    }

    public function paginateCollection(Request $request)
    {
        $queryBuilder = new GenericQueryBuilderService($request, Person::class);
        $result = $queryBuilder->getCollectionsPaginated();
        return response()->json($result);
    }
}
```

## Step 5: Update Your Models (for Multitenant Support)

Add the multitenant field to your models:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Person extends Model
{
    public static $multitenantField = ['enseigne_id'];

    // ... rest of your model
}
```

## Step 6: Generate Routes

### Automatic Generation (Recommended)

Set in your `.env` file:
```env
AUTO_CRUD_GENERATE_ROUTES=true
```

### Manual Generation

Use the Artisan command:
```bash
# Generate routes for all configured models
php artisan auto-crud:generate-routes

# Or scan for all models in app/Models
php artisan auto-crud:generate-routes --scan

# Or generate for a specific model
php artisan auto-crud:generate-routes --model="App\Models\Person"

# Dry run to see what would be generated
php artisan auto-crud:generate-routes --scan --dry-run
```

## Step 7: Update Your Route Files (if using manual routes)

If you prefer to define routes manually, you can use the package in your route files:

```php
<?php

use Illuminate\Support\Facades\Route;
use FivoTech\LaravelAutoCrud\Controllers\AutoCrudController;
use App\Models\Person;

Route::middleware(['api', 'auth:sanctum'])->group(function () {
    Route::get('/persons', function (Request $request) {
        $controller = new AutoCrudController(Person::class);
        return $controller->index($request);
    });

    Route::get('/persons/paginate', function (Request $request) {
        $controller = new AutoCrudController(Person::class);
        return $controller->paginateCollection($request);
    });

    // Add more routes as needed
});
```

## Step 8: Test Your Setup

Test with some API calls:

```bash
# Get all persons
curl -X GET "http://your-app.com/api/persons?enseigneId=1" \
     -H "Authorization: Bearer YOUR_TOKEN"

# Get paginated persons
curl -X GET "http://your-app.com/api/persons/paginate?enseigneId=1&per_page=10" \
     -H "Authorization: Bearer YOUR_TOKEN"

# Get persons with filters
curl -X GET "http://your-app.com/api/persons?enseigneId=1&filters=[[\"name\",\"like\",\"%john%\"]]" \
     -H "Authorization: Bearer YOUR_TOKEN"

# Create a person
curl -X POST "http://your-app.com/api/persons" \
     -H "Authorization: Bearer YOUR_TOKEN" \
     -H "Content-Type: application/json" \
     -d '{"enseigneId": 1, "name": "John Doe", "email": "john@example.com"}'
```

## Environment Variables

Add these to your `.env` file:

```env
# Auto CRUD Configuration
AUTO_CRUD_GENERATE_ROUTES=true
AUTO_CRUD_ROUTE_PREFIX=api
AUTO_CRUD_MULTITENANT_ENABLED=true
AUTO_CRUD_ENABLE_CACHING=false
AUTO_CRUD_CACHE_TTL=3600
```

## Troubleshooting

### Routes not generating
- Make sure `AUTO_CRUD_GENERATE_ROUTES=true` in your .env file
- Check that your models are properly configured in `config/auto-crud.php`
- Run `php artisan route:list` to see if routes are registered

### Multitenant access denied
- Ensure the user has access to the specified `enseigneId`
- Check that the `UsersEnseignes` model exists and has the correct data
- Verify that `enseigne_id` is included in the model's `$multitenantField` array

### JSON parameter errors
- Ensure JSON parameters are properly formatted
- Use proper URL encoding when passing JSON in query parameters

### Controller not found
- Make sure the controller class exists and is properly namespaced
- Verify the controller extends the base Controller class
- Check that the controller implements the required methods







