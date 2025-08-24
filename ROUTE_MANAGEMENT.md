# Route Management & Reset Guide

## Overview
The Auto CRUD package now includes comprehensive route management with tracking, reset functionality, and conflict prevention.

## Route Tracking System
- **Metadata Storage**: Routes are tracked in Laravel Cache with timestamps
- **Persistent Tracking**: Routes remain tracked across requests
- **Model Association**: Each route is linked to its source model
- **Conflict Prevention**: Prevents duplicate route generation

## Commands Available

### 1. Generate Routes Command
```bash
# Generate routes from config
php artisan auto-crud:generate-routes

# Generate with automatic reset of existing routes
php artisan auto-crud:generate-routes --reset

# Dry run to preview routes
php artisan auto-crud:generate-routes --dry-run

# Validate for conflicts without generating
php artisan auto-crud:generate-routes --validate

# Generate for specific model
php artisan auto-crud:generate-routes --model="App\Models\User"

# Scan and generate for discovered models
php artisan auto-crud:generate-routes --scan
```

### 2. Reset Routes Command
```bash
# Show current auto-generated routes
php artisan auto-crud:reset-routes --show

# Reset all auto-generated routes (with confirmation)
php artisan auto-crud:reset-routes --all

# Reset routes for specific models
php artisan auto-crud:reset-routes --models="App\Models\User,App\Models\Post"

# Force reset without confirmation
php artisan auto-crud:reset-routes --all --force

# Clean up stale metadata
php artisan auto-crud:reset-routes --cleanup
```

## Configuration Options

### Auto-Reset Configuration
```php
// config/auto-crud.php
return [
    // Automatically reset routes when config changes
    'auto_reset_on_config_change' => true,

    // Prevent route conflicts
    'prevent_route_conflicts' => true,

    // Other options...
];
```

### Environment Variables
```env
# Enable auto-reset on config changes
AUTO_CRUD_AUTO_RESET=true

# Enable conflict prevention
AUTO_CRUD_PREVENT_CONFLICTS=true
```

## Route Metadata Structure
Each generated route is tracked with:
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

## Use Cases & Workflows

### 1. Development Workflow
```bash
# 1. Validate routes before generating
php artisan auto-crud:generate-routes --validate

# 2. Generate with dry-run to preview
php artisan auto-crud:generate-routes --dry-run

# 3. Generate routes with auto-reset
php artisan auto-crud:generate-routes --reset

# 4. Check what was generated
php artisan auto-crud:reset-routes --show
```

### 2. Updating Models
```bash
# Reset routes for specific models before regenerating
php artisan auto-crud:reset-routes --models="App\Models\User"

# Regenerate for specific model
php artisan auto-crud:generate-routes --model="App\Models\User"
```

### 3. Clean Deployment
```bash
# Reset all routes before deployment
php artisan auto-crud:reset-routes --all --force

# Generate fresh routes
php artisan auto-crud:generate-routes

# Cleanup any stale metadata
php artisan auto-crud:reset-routes --cleanup
```

### 4. Debugging Route Issues
```bash
# Show all tracked routes
php artisan auto-crud:reset-routes --show

# Validate metadata consistency
php artisan auto-crud:reset-routes --cleanup

# Check for route conflicts
php artisan auto-crud:generate-routes --validate
```

## Safety Features

### 1. Confirmation Prompts
- Reset commands require confirmation by default
- Use `--force` to skip prompts in scripts
- Clear warnings about destructive operations

### 2. Conflict Detection
- Checks for existing route names and patterns
- Prevents overriding manually defined routes
- Logs conflicts for review

### 3. Metadata Validation
- Tracks route generation timestamps
- Validates metadata consistency
- Cleanup tools for stale entries

### 4. Selective Operations
- Reset specific models without affecting others
- Generate routes incrementally
- Granular control over route management

## Error Handling
- Graceful failure with detailed error messages
- Logging of failed operations
- Recovery mechanisms for corrupted metadata

## Performance Considerations
- Efficient cache-based metadata storage
- Lazy loading of route collections
- Minimal overhead during route generation

## Best Practices

### 1. Always Validate First
```bash
php artisan auto-crud:generate-routes --validate
```

### 2. Use Dry-Run for Testing
```bash
php artisan auto-crud:generate-routes --dry-run
```

### 3. Reset Before Major Changes
```bash
php artisan auto-crud:reset-routes --all
```

### 4. Regular Metadata Cleanup
```bash
php artisan auto-crud:reset-routes --cleanup
```

### 5. Model-Specific Updates
```bash
# Instead of resetting all routes
php artisan auto-crud:reset-routes --models="App\Models\User"
```

This comprehensive route management system ensures safe, trackable, and reversible route generation for your Laravel Auto CRUD package!
