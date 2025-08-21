# Changelog

All notable changes to the Laravel Auto CRUD package will be documented in this file.

## [1.0.0] - 2024-12-19

### Added
- Initial release of Laravel Auto CRUD package
- **GenericQueryBuilderService**: Advanced query building with support for:
  - Dynamic filters (AND/OR conditions)
  - WhereIn filters (single and multiple)
  - Relationship loading with conditional filters
  - Ordering and sorting
  - Field selection
  - Grouping
  - Pagination with configurable limits
  - Query result caching
- **AutoCrudController**: Base controller with automatic CRUD operations:
  - RESTful methods (index, store, show, update, destroy)
  - Additional methods (paginateCollection, getOne)
  - Multitenant support with enseigne-based access control
  - Automatic many-to-many relationship handling
  - Bulk insert operations
  - Error handling and consistent responses
- **AutoRouteGeneratorService**: Automatic route generation:
  - Scan for Eloquent models
  - Generate routes based on model configuration
  - Configurable HTTP methods and route patterns
  - Middleware assignment
  - Route naming conventions
- **Configuration System**: Comprehensive configuration options:
  - Model-specific settings
  - Multitenant configuration
  - Query builder settings
  - Security options
  - Route generation settings
- **Artisan Commands**:
  - `auto-crud:generate-routes`: Generate routes for models
  - Support for scanning, dry-run, and specific model targeting
- **Service Provider**: Auto-discovery and configuration publishing
- **Facade Support**: Easy access through Laravel facades
- **Multitenant Features**:
  - Automatic enseigne verification
  - User-enseigne relationship caching
  - Configurable field names and models
  - Method-level exclusions
- **Security Features**:
  - JSON parameter validation
  - Input sanitization
  - Configurable pagination limits
  - Error handling without information leakage
- **Caching Support**:
  - Query result caching
  - Configurable TTL
  - Cache key generation
  - Cache invalidation
- **Many-to-Many Relationship Support**:
  - Automatic detection of `_ids` fields
  - Pivot table synchronization
  - Create and update operations
- **Bulk Operations**:
  - Bulk insert mode
  - Automatic enseigne field injection
  - Performance-optimized inserts

### Features in Detail

#### GenericQueryBuilderService
- Support for complex JSON query parameters
- Relationship filtering with `whereHas`
- Multiple filter types: exact match, like, comparison operators
- Automatic query optimization
- Memory-efficient pagination
- Optional result caching

#### AutoCrudController
- Extends standard Laravel Controller
- Implements comprehensive CRUD interface
- Automatic error handling and logging
- Multitenant access control
- Support for model-specific configurations
- Bulk operation support

#### Route Generation
- Automatic discovery of Eloquent models
- Configurable route patterns and naming
- Middleware assignment per model or globally
- Method inclusion/exclusion per model
- RESTful route conventions

#### Multitenant Support
- Configurable tenant field (default: enseigne_id)
- User-tenant relationship verification
- Automatic tenant field injection
- Caching of user-tenant relationships
- Method-level tenant check exclusions

### Configuration Options
- `auto_generate_routes`: Enable/disable automatic route generation
- `route_prefix`: API route prefix
- `middleware`: Default middleware stack
- `models`: Model-specific configurations
- `multitenant`: Multitenant settings
- `query_builder`: Query builder configuration
- `security`: Security-related settings

### Compatibility
- Laravel 10.x and 11.x
- PHP 8.1+
- MySQL, PostgreSQL, SQLite support
- Compatible with Laravel Sanctum authentication

### Documentation
- Comprehensive README with usage examples
- Installation guide with step-by-step instructions
- Migration guide for existing implementations
- API documentation with query parameter examples
- Configuration reference

### Testing
- Unit tests for core functionality
- Integration tests for route generation
- Example implementations and demos
- Dry-run capabilities for safe testing





