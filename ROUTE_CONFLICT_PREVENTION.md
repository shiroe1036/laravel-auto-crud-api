# Route Conflict Prevention Guide

## Overview
This package now includes comprehensive route conflict detection and prevention mechanisms to ensure it works safely with existing Laravel projects that have routes defined in `api.php`.

## Critical Fixes Implemented

### 1. Route Registration Timing ✅
- **Problem**: Routes were registered during service provider `boot()`, potentially before `api.php` routes
- **Solution**: Now uses `app->booted()` to ensure registration happens AFTER all route files are loaded

### 2. Route Conflict Detection ✅
- **Problem**: No checks for existing routes or route names
- **Solution**: Added comprehensive conflict detection with `hasRouteConflict()` method

### 3. Route Pattern Analysis ✅
- **Problem**: Could generate routes that conflict with existing patterns
- **Solution**: Added `patternsConflict()` method to detect URI pattern conflicts

### 4. Route Validation Command ✅
- **Problem**: No way to test for conflicts before generating routes
- **Solution**: Added `--validate` flag to check for conflicts without generating routes

## Usage Examples

### Safe Configuration
```php
// config/auto-crud.php
return [
    'auto_generate_routes' => false, // Disabled by default
    'route_prefix' => 'auto-crud',   // Isolated prefix
    'prevent_route_conflicts' => true, // Enable conflict detection
    'route_name_pattern' => 'auto-crud.{resource}.{method}', // Isolated naming
];
```

### Validate Before Generation
```bash
# Check for conflicts without generating routes
php artisan auto-crud:generate-routes --validate

# Generate routes with dry-run to see what would be created
php artisan auto-crud:generate-routes --dry-run
```

### Safe Route Patterns
Instead of conflicting patterns like:
```
/api/users (conflicts with existing API routes)
/api/posts (conflicts with existing API routes)
```

The package now generates isolated patterns:
```
/auto-crud/users (isolated prefix)
/auto-crud/posts (isolated prefix)
```

## Conflict Detection Features

### Route Name Conflicts
- Checks `hasNamedRoute()` to prevent duplicate route names
- Uses isolated naming patterns like `auto-crud.users.index`

### Route Pattern Conflicts
- Analyzes URI patterns for parameter conflicts
- Prevents `/api/{resource}` from conflicting with `/api/auth`
- Handles complex parameter matching scenarios

### HTTP Method Conflicts
- Only checks conflicts for matching HTTP methods
- Allows same pattern with different methods when appropriate

## Environment Variables
```env
# Disable auto-generation in production
AUTO_CRUD_GENERATE_ROUTES=false

# Use isolated prefix
AUTO_CRUD_ROUTE_PREFIX=auto-crud

# Enable conflict prevention
AUTO_CRUD_PREVENT_CONFLICTS=true

# Use isolated naming
AUTO_CRUD_ROUTE_NAME_PATTERN=auto-crud.{resource}.{method}
```

## Migration Guide for Existing Projects

1. **Disable Auto-Generation**:
   ```php
   'auto_generate_routes' => false,
   ```

2. **Validate Existing Setup**:
   ```bash
   php artisan auto-crud:generate-routes --validate
   ```

3. **Use Isolated Configuration**:
   ```bash
   cp config/auto-crud-safe.php config/auto-crud.php
   ```

4. **Test with Dry Run**:
   ```bash
   php artisan auto-crud:generate-routes --dry-run
   ```

5. **Enable Gradually**:
   ```php
   'auto_generate_routes' => true,
   ```

## Troubleshooting

### Common Conflicts
- Route names: Use `route_name_pattern` with prefixes
- URI patterns: Use different `route_prefix`
- Middleware: Check middleware stack compatibility

### Debugging
```bash
# View all conflicts
php artisan auto-crud:generate-routes --validate

# Check specific model
php artisan auto-crud:generate-routes --model="App\Models\User" --dry-run
```

This implementation ensures your package can be safely used in existing Laravel projects without breaking existing API routes.
