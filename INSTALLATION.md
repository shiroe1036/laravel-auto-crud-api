# Installation and Setup Guide

## Step 1: Install the Package

Add the package to your Laravel project's composer.json:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "./packages/laravel-auto-crud"
        }
    ],
    "require": {
        "fivotech/laravel-auto-crud": "dev-main"
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







