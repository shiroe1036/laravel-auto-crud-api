# Quick Reference - Laravel Auto CRUD Commands

## Route Generation Commands

### Basic Generation
```bash
# Generate routes from configuration
php artisan auto-crud:generate-routes

# Generate with automatic reset
php artisan auto-crud:generate-routes --reset

# Preview without generating (dry-run)
php artisan auto-crud:generate-routes --dry-run
```

### Validation & Conflict Detection
```bash
# Check for route conflicts before generating
php artisan auto-crud:generate-routes --validate

# Validate specific model
php artisan auto-crud:generate-routes --model="App\Models\User" --validate
```

### Model-Specific Generation
```bash
# Generate for specific model
php artisan auto-crud:generate-routes --model="App\Models\User"

# Dry-run for specific model
php artisan auto-crud:generate-routes --model="App\Models\User" --dry-run
```

### Auto-Discovery
```bash
# Scan and generate for all models
php artisan auto-crud:generate-routes --scan

# Scan specific directory
php artisan auto-crud:generate-routes --scan --directory="app/Models"

# Scan with dry-run
php artisan auto-crud:generate-routes --scan --dry-run
```

## Route Reset Commands

### View Current Routes
```bash
# Show all auto-generated routes with metadata
php artisan auto-crud:reset-routes --show
```

### Reset All Routes
```bash
# Reset all routes (with confirmation)
php artisan auto-crud:reset-routes --all

# Reset all routes without confirmation
php artisan auto-crud:reset-routes --all --force
```

### Reset Specific Models
```bash
# Reset routes for specific models
php artisan auto-crud:reset-routes --models="App\Models\User"

# Reset multiple models
php artisan auto-crud:reset-routes --models="App\Models\User,App\Models\Post"

# Reset specific models without confirmation
php artisan auto-crud:reset-routes --models="App\Models\User" --force
```

### Maintenance
```bash
# Clean up stale metadata
php artisan auto-crud:reset-routes --cleanup
```

## Common Workflows

### Development Workflow
```bash
# 1. Validate before generating
php artisan auto-crud:generate-routes --validate

# 2. Preview with dry-run
php artisan auto-crud:generate-routes --dry-run

# 3. Generate with clean state
php artisan auto-crud:generate-routes --reset

# 4. Check what was generated
php artisan auto-crud:reset-routes --show
```

### Model Updates
```bash
# 1. Reset routes for specific model
php artisan auto-crud:reset-routes --models="App\Models\User"

# 2. Regenerate for that model
php artisan auto-crud:generate-routes --model="App\Models\User"
```

### Clean Deployment
```bash
# 1. Reset all existing routes
php artisan auto-crud:reset-routes --all --force

# 2. Validate configuration
php artisan auto-crud:generate-routes --validate

# 3. Generate fresh routes
php artisan auto-crud:generate-routes

# 4. Cleanup metadata
php artisan auto-crud:reset-routes --cleanup
```

### Debugging Issues
```bash
# Check current route state
php artisan auto-crud:reset-routes --show

# Validate for conflicts
php artisan auto-crud:generate-routes --validate

# Clean up stale data
php artisan auto-crud:reset-routes --cleanup

# Show Laravel routes (built-in command)
php artisan route:list --name=auto-crud
```

## Configuration Quick Setup

### Environment Variables
```env
# Basic settings
AUTO_CRUD_GENERATE_ROUTES=false
AUTO_CRUD_ROUTE_PREFIX=auto-crud
AUTO_CRUD_PREVENT_CONFLICTS=true
AUTO_CRUD_AUTO_RESET=true
AUTO_CRUD_ROUTE_NAME_PATTERN=auto-crud.{resource}.{method}
```

### Safe Configuration for Existing Projects
```php
// config/auto-crud.php
return [
    'auto_generate_routes' => false,
    'route_prefix' => 'auto-crud',
    'prevent_route_conflicts' => true,
    'auto_reset_on_config_change' => true,
    'route_name_pattern' => 'auto-crud.{resource}.{method}',
    'models' => [
        App\Models\User::class => [
            'exclude_methods' => ['destroy'],
        ],
    ],
];
```

## Command Options Reference

### Generate Routes Options
| Option | Description |
|--------|-------------|
| `--scan` | Auto-discover models |
| `--model=` | Target specific model |
| `--directory=` | Scan specific directory |
| `--validate` | Check for conflicts |
| `--reset` | Reset before generating |
| `--dry-run` | Preview without generating |

### Reset Routes Options
| Option | Description |
|--------|-------------|
| `--show` | Display current routes |
| `--all` | Reset all routes |
| `--models=` | Reset specific models |
| `--force` | Skip confirmations |
| `--cleanup` | Clean stale metadata |

## Exit Codes
- `0` - Success
- `1` - Error or conflicts detected

## Examples with Expected Output

### Validation with Conflicts
```bash
$ php artisan auto-crud:generate-routes --validate

⚠️ Route conflicts detected:
Model  Method  HTTP  Pattern     Name           Reason
User   index   GET   api/users   users.index    Route name already exists
Post   show    GET   api/posts/1 posts.show     Route pattern conflicts
```

### Successful Generation
```bash
$ php artisan auto-crud:generate-routes

✅ Routes generated for: App\Models\User
✅ Routes generated for: App\Models\Post
✅ Route generation complete. Generated routes for 2 models.
```

### Route Metadata Display
```bash
$ php artisan auto-crud:reset-routes --show

Route Name              Model  Method  HTTP    Pattern           Generated At
auto-crud.users.index   User   index   GET     users            2025-08-24 10:30:00
auto-crud.users.store   User   store   POST    users            2025-08-24 10:30:00
auto-crud.users.show    User   show    GET     users/{id}       2025-08-24 10:30:00
```
