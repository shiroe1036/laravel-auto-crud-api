<?php

namespace FivoTech\LaravelAutoCrud\Services;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Routing\Router;
use ReflectionClass;
use ReflectionMethod;

/**
 * Auto Route Generator Service
 *
 * Handles automatic CRUD route generation with conflict detection,
 * metadata tracking, and route management capabilities.
 */
class AutoRouteGeneratorService
{
    protected array $config;
    protected array $conflictLog = [];
    protected bool $preventConflicts = true;
    protected string $metadataCacheKey = 'auto_crud_route_metadata';

    public function __construct()
    {
        $this->config = config('auto-crud', []);
        $this->preventConflicts = $this->config['prevent_route_conflicts'] ?? true;
    }

    // ========================================
    // PUBLIC API - Route Generation
    // ========================================

    /**
     * Generate routes for all configured models
     */
    public function generateRoutes(): void
    {
        $this->initializeGeneration();
        $this->processConfiguredModels();
        $this->handleConflictLogging();
    }

    /**
     * Generate routes for a specific model
     */
    public function generateRoutesForModel(string $modelClass, array $modelConfig = []): void
    {
        $routeDefinition = $this->createRouteDefinition($modelClass, $modelConfig);
        $this->registerModelRoutes($routeDefinition);
    }

    // ========================================
    // PUBLIC API - Route Information
    // ========================================

    /**
     * Get route information for a model
     */
    public function getModelRouteInfo(string $modelClass, array $modelConfig = []): array
    {
        $routeDefinition = $this->createRouteDefinition($modelClass, $modelConfig);
        return $this->buildRouteInfo($routeDefinition);
    }

    /**
     * Get all conflicts that were detected during route generation
     */
    public function getConflicts(): array
    {
        return $this->conflictLog;
    }

    // ========================================
    // PUBLIC API - Model Discovery
    // ========================================

    /**
     * Scan for models in the application
     */
    public function scanForModels(string $directory = null): array
    {
        $directory = $directory ?? app_path('Models');

        if (!is_dir($directory)) {
            return [];
        }

        return $this->discoverModelsInDirectory($directory);
    }

    /**
     * Generate routes for discovered models
     */
    public function generateRoutesForDiscoveredModels(string $directory = null): array
    {
        $models = $this->scanForModels($directory);
        $generatedRoutes = [];

        foreach ($models as $modelClass) {
            $this->generateRoutesForModel($modelClass);
            $generatedRoutes[] = $modelClass;
        }

        return $generatedRoutes;
    }

    // ========================================
    // PRIVATE METHODS - Route Generation Core
    // ========================================

    /**
     * Initialize the route generation process
     */
    private function initializeGeneration(): void
    {
        $this->conflictLog = [];

        // Check if we should skip generation entirely
        if ($this->shouldSkipGeneration()) {
            if (app()->runningInConsole()) {
                echo "✓ Routes already generated and configuration unchanged. Skipping generation.\n";
            }
            return;
        }

        // Store current config hash for future comparisons
        $this->storeConfigHash();

        // Only attempt reset if explicitly requested via config
        if ($this->config['auto_reset_on_config_change'] ?? false) {
            // Clear metadata only - routes will be overridden
            Cache::forget($this->metadataCacheKey);
        }
    }

    /**
     * Process all configured models
     */
    private function processConfiguredModels(): void
    {
        $models = $this->config['models'] ?? [];

        foreach ($models as $modelClass => $modelConfig) {
            $this->generateRoutesForModel($modelClass, $modelConfig);
        }
    }

    /**
     * Handle conflict logging after generation
     */
    private function handleConflictLogging(): void
    {
        if (!empty($this->conflictLog) && $this->preventConflicts) {
            $this->logRouteConflicts();
        }
    }

    /**
     * Create a route definition structure for a model
     */
    private function createRouteDefinition(string $modelClass, array $modelConfig): array
    {
        return [
            'model' => $modelClass,
            'config' => $modelConfig,
            'controller' => $modelConfig['controller'] ?? $this->config['default_controller'],
            'resource_name' => $this->getResourceName($modelClass, $modelConfig),
            'middleware' => $this->mergeMiddleware($modelConfig),
            'available_methods' => null, // Will be populated when needed
        ];
    }

    /**
     * Register routes for a model using its route definition
     */
    private function registerModelRoutes(array $routeDefinition): void
    {
        $routeDefinition['available_methods'] = $this->getAvailableMethods(
            $routeDefinition['controller'],
            $routeDefinition['config']
        );

        $routeGroup = $this->createRouteGroup($routeDefinition['middleware']);

        $routeGroup->group(function () use ($routeDefinition) {
            foreach ($routeDefinition['available_methods'] as $method) {
                $this->generateRouteForMethod($routeDefinition, $method);
            }
        });
    }

    /**
     * Create a route group with proper configuration
     */
    private function createRouteGroup(array $middleware): \Illuminate\Routing\RouteRegistrar
    {
        $routeGroup = Route::prefix($this->config['route_prefix'] ?? 'api')
            ->middleware($middleware);

        if (!empty($this->config['route_namespace'])) {
            $routeGroup = $routeGroup->namespace($this->config['route_namespace']);
        }

        return $routeGroup;
    }

    /**
     * Merge middleware from global config and model config
     */
    private function mergeMiddleware(array $modelConfig): array
    {
        return array_merge(
            $this->config['middleware'] ?? [],
            $modelConfig['middleware'] ?? []
        );
    }

    /**
     * Generate a route for a specific method
     */
    protected function generateRouteForMethod(array $routeDefinition, string $method): void
    {
        $methodInfo = $this->getMethodInfo($method, $routeDefinition);

        if (!$methodInfo) {
            return;
        }

        if ($this->shouldSkipRouteForConflict($methodInfo)) {
            $this->logConflict($methodInfo, $routeDefinition);
            return;
        }

        $this->createAndRegisterRoute($methodInfo, $routeDefinition);
        $this->trackGeneratedRoute($methodInfo, $routeDefinition);
    }

    /**
     * Get method information for route generation
     */
    private function getMethodInfo(string $method, array $routeDefinition): ?array
    {
        $crudMethods = $this->config['crud_methods'] ?? [];

        if (!isset($crudMethods[$method])) {
            return null;
        }

        $methodConfig = $crudMethods[$method];
        $routePattern = str_replace('{resource}', $routeDefinition['resource_name'], $methodConfig['route_pattern']);
        $routeName = $this->generateRouteName($routeDefinition['resource_name'], $method, $routeDefinition['config']);

        return [
            'method' => $method,
            'config' => $methodConfig,
            'http_method' => strtolower($methodConfig['http_method']),
            'route_pattern' => $routePattern,
            'route_name' => $routeName,
        ];
    }

    /**
     * Check if route should be skipped due to conflicts
     */
    private function shouldSkipRouteForConflict(array $methodInfo): bool
    {
        return $this->preventConflicts && $this->hasRouteConflict(
            $methodInfo['route_pattern'],
            $methodInfo['route_name'],
            $methodInfo['http_method']
        );
    }

    /**
     * Log a route conflict
     */
    private function logConflict(array $methodInfo, array $routeDefinition): void
    {
        $this->conflictLog[] = [
            'model' => $routeDefinition['model'],
            'method' => $methodInfo['method'],
            'route_pattern' => $methodInfo['route_pattern'],
            'route_name' => $methodInfo['route_name'],
            'http_method' => strtoupper($methodInfo['http_method']),
            'reason' => 'Route pattern or name already exists'
        ];
    }

    /**
     * Create and register the actual route
     */
    private function createAndRegisterRoute(array $methodInfo, array $routeDefinition): void
    {
        $route = Route::{$methodInfo['http_method']}(
            $methodInfo['route_pattern'],
            $this->createRouteHandler($routeDefinition, $methodInfo['method'])
        )->name($methodInfo['route_name']);

        $this->applyRouteConstraints($route, $methodInfo['config']);
    }

    /**
     * Create route handler closure
     */
    private function createRouteHandler(array $routeDefinition, string $method): \Closure
    {
        return function (...$args) use ($routeDefinition, $method) {
            $controllerInstance = new $routeDefinition['controller']();

            if (method_exists($controllerInstance, 'setModel')) {
                $controllerInstance->setModel($routeDefinition['model']);
            }

            $controllerInstance = $this->applyHooksToController($controllerInstance, $routeDefinition);

            return call_user_func_array([$controllerInstance, $method], $args);
        };
    }

    /**
     * Apply route constraints if defined
     */
    private function applyRouteConstraints($route, array $methodConfig): void
    {
        if (isset($methodConfig['where']) && is_array($methodConfig['where'])) {
            $route->where($methodConfig['where']);
        }
    }

    /**
     * Track a generated route in metadata
     */
    private function trackGeneratedRoute(array $methodInfo, array $routeDefinition): void
    {
        $metadata = Cache::get($this->metadataCacheKey, []);

        $metadata[$methodInfo['route_name']] = [
            'model' => $routeDefinition['model'],
            'method' => $methodInfo['method'],
            'pattern' => $methodInfo['route_pattern'],
            'http_method' => strtoupper($methodInfo['http_method']),
            'generated_at' => now()->toISOString(),
        ];

        Cache::put($this->metadataCacheKey, $metadata, now()->addDays(30));
    }

    /**
     * Build route information for display/analysis
     */
    private function buildRouteInfo(array $routeDefinition): array
    {
        $routeDefinition['available_methods'] = $this->getAvailableMethods(
            $routeDefinition['controller'],
            $routeDefinition['config']
        );

        $routes = [];
        foreach ($routeDefinition['available_methods'] as $method) {
            $methodInfo = $this->getMethodInfo($method, $routeDefinition);
            if ($methodInfo) {
                $routes[] = [
                    'method' => $method,
                    'http_method' => $methodInfo['config']['http_method'],
                    'pattern' => $methodInfo['route_pattern'],
                    'name' => $methodInfo['route_name'],
                    'controller' => $routeDefinition['controller'],
                ];
            }
        }

        return [
            'model' => $routeDefinition['model'],
            'resource_name' => $routeDefinition['resource_name'],
            'controller' => $routeDefinition['controller'],
            'routes' => $routes,
        ];
    }

    /**
     * Discover models in a directory
     */
    private function discoverModelsInDirectory(string $directory): array
    {
        $models = [];
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory)
        );

        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $className = $this->getClassNameFromFile($file->getPathname());

                if ($className && $this->isEloquentModel($className)) {
                    $models[] = $className;
                }
            }
        }

        return $models;
    }

    /**
     * Apply hooks to controller instance
     */
    protected function applyHooksToController($controllerInstance, array $routeDefinition)
    {
        $hooks = $routeDefinition['config']['hooks'] ?? [];
        $globalHooks = $this->config['global_hooks'] ?? [];
        $allHooks = array_merge($globalHooks, $hooks);

        if (empty($allHooks)) {
            return $controllerInstance;
        }

        if (method_exists($controllerInstance, '__construct')) {
            $reflection = new \ReflectionClass($controllerInstance);
            $constructor = $reflection->getConstructor();

            if ($constructor) {
                $parameters = $constructor->getParameters();
                foreach ($parameters as $parameter) {
                    if ($parameter->getName() === 'options' && !empty($allHooks)) {
                        $model = $controllerInstance->model ?? null;
                        return new ($reflection->getName())($model, ['hooks' => $allHooks]);
                    }
                }
            }
        }

        return $controllerInstance;
    }

    // ========================================
    // PRIVATE METHODS - Model Analysis
    // ========================================

    /**
     * Get available methods for a controller
     */
    protected function getAvailableMethods(string $controller, array $modelConfig): array
    {
        $crudMethods = array_keys($this->config['crud_methods'] ?? []);

        // If include_methods is specified, only use those
        if (!empty($modelConfig['include_methods'])) {
            $crudMethods = array_intersect($crudMethods, $modelConfig['include_methods']);
        }

        // Remove excluded methods
        if (!empty($modelConfig['exclude_methods'])) {
            $crudMethods = array_diff($crudMethods, $modelConfig['exclude_methods']);
        }

        // Only include methods that actually exist in the controller
        $reflection = new ReflectionClass($controller);
        $controllerMethods = array_map(
            fn(ReflectionMethod $method) => $method->getName(),
            $reflection->getMethods(ReflectionMethod::IS_PUBLIC)
        );

        $availableMethods = array_intersect($crudMethods, $controllerMethods);

        // Ensure proper route precedence: specific routes before parameterized routes
        $availableMethods = $this->sortMethodsByRoutePrecedence($availableMethods);

        return $availableMethods;
    }

    /**
     * Sort methods by route precedence to avoid route conflicts
     * Specific routes (like /paginate) must come before parameterized routes (like /{id})
     */
    protected function sortMethodsByRoutePrecedence(array $methods): array
    {
        $crudMethods = $this->config['crud_methods'] ?? [];

        // Define precedence groups
        $staticRoutes = []; // Routes without parameters
        $parameterizedRoutes = []; // Routes with parameters like {id}

        foreach ($methods as $method) {
            if (!isset($crudMethods[$method])) {
                continue;
            }

            $routePattern = $crudMethods[$method]['route_pattern'] ?? '';

            // Check if route has parameters
            if (strpos($routePattern, '{') !== false) {
                $parameterizedRoutes[] = $method;
            } else {
                $staticRoutes[] = $method;
            }
        }

        // Static routes first, then parameterized routes
        return array_merge($staticRoutes, $parameterizedRoutes);
    }

    /**
     * Get resource name from model class
     */
    protected function getResourceName(string $modelClass, array $modelConfig): string
    {
        if (isset($modelConfig['route_name_prefix'])) {
            return $modelConfig['route_name_prefix'];
        }

        $className = class_basename($modelClass);
        return Str::kebab(Str::plural($className));
    }

    /**
     * Generate route name
     */
    protected function generateRouteName(string $resourceName, string $method, array $modelConfig): string
    {
        $pattern = $this->config['route_name_pattern'] ?? '{resource}.{method}';

        return str_replace(
            ['{resource}', '{method}'],
            [$resourceName, $method],
            $pattern
        );
    }

    /**
     * Get class name from file
     */
    protected function getClassNameFromFile(string $filepath): ?string
    {
        $content = file_get_contents($filepath);

        // Extract namespace
        $namespace = '';
        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            $namespace = $matches[1] . '\\';
        }

        // Extract class name
        if (preg_match('/class\s+(\w+)/', $content, $matches)) {
            return $namespace . $matches[1];
        }

        return null;
    }

    /**
     * Check if class is an Eloquent model
     */
    protected function isEloquentModel(string $className): bool
    {
        if (!class_exists($className)) {
            return false;
        }

        $reflection = new ReflectionClass($className);

        return $reflection->isSubclassOf(\Illuminate\Database\Eloquent\Model::class) &&
            !$reflection->isAbstract();
    }

    // ========================================
    // PUBLIC API - Route Validation & Conflict Detection
    // ========================================

    /**
     * Check if routes can be safely generated without conflicts
     */
    public function validateRoutes(): array
    {
        $originalPreventConflicts = $this->preventConflicts;
        $originalConflictLog = $this->conflictLog;

        // Enable conflict detection for validation
        $this->preventConflicts = true;
        $this->conflictLog = [];

        // Simulate route generation to detect conflicts
        $models = $this->config['models'] ?? [];
        foreach ($models as $modelClass => $modelConfig) {
            $this->validateModelRoutes($modelClass, $modelConfig);
        }

        $conflicts = $this->conflictLog;

        // Restore original state
        $this->preventConflicts = $originalPreventConflicts;
        $this->conflictLog = $originalConflictLog;

        return $conflicts;
    }

    /**
     * Check if a route conflicts with existing routes
     */
    protected function hasRouteConflict(string $routePattern, string $routeName, string $httpMethod): bool
    {
        $router = app('router');
        $existingRoutes = $router->getRoutes();

        // Check for route name conflicts
        if ($existingRoutes->hasNamedRoute($routeName)) {
            return true;
        }

        // Build full pattern with prefix
        $prefix = $this->config['route_prefix'] ?? 'api';
        $fullPattern = $prefix . '/' . ltrim($routePattern, '/');

        // Check for route pattern conflicts
        foreach ($existingRoutes as $route) {
            // Only check routes with the same HTTP method
            if (!in_array(strtoupper($httpMethod), $route->methods())) {
                continue;
            }

            $existingPattern = $route->uri();

            // Check for exact pattern match
            if ($existingPattern === $fullPattern || $existingPattern === ltrim($routePattern, '/')) {
                return true;
            }

            // Check for potential parameter conflicts
            if ($this->patternsConflict($fullPattern, $existingPattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate routes for a specific model without actually creating them
     */
    protected function validateModelRoutes(string $modelClass, array $modelConfig): void
    {
        $routeDefinition = $this->createRouteDefinition($modelClass, $modelConfig);
        $routeDefinition['available_methods'] = $this->getAvailableMethods(
            $routeDefinition['controller'],
            $routeDefinition['config']
        );

        foreach ($routeDefinition['available_methods'] as $method) {
            $methodInfo = $this->getMethodInfo($method, $routeDefinition);
            if (!$methodInfo) {
                continue;
            }

            if ($this->hasRouteConflict($methodInfo['route_pattern'], $methodInfo['route_name'], $methodInfo['http_method'])) {
                $this->conflictLog[] = [
                    'model' => $modelClass,
                    'method' => $method,
                    'route_pattern' => $methodInfo['route_pattern'],
                    'route_name' => $methodInfo['route_name'],
                    'http_method' => strtoupper($methodInfo['http_method']),
                    'reason' => 'Route pattern or name already exists'
                ];
            }
        }
    }

    /**
     * Check if two route patterns could conflict
     */
    protected function patternsConflict(string $pattern1, string $pattern2): bool
    {
        // Remove leading slashes for comparison
        $pattern1 = ltrim($pattern1, '/');
        $pattern2 = ltrim($pattern2, '/');

        // Skip if patterns are identical (handled elsewhere)
        if ($pattern1 === $pattern2) {
            return false;
        }

        // Split patterns into segments
        $segments1 = explode('/', $pattern1);
        $segments2 = explode('/', $pattern2);

        // Different number of segments usually means no conflict
        if (count($segments1) !== count($segments2)) {
            return false;
        }

        $hasParameterOverlap = false;

        // Compare each segment
        for ($i = 0; $i < count($segments1); $i++) {
            $seg1 = $segments1[$i];
            $seg2 = $segments2[$i];

            // If both are parameters, they could conflict
            if ($this->isParameter($seg1) && $this->isParameter($seg2)) {
                $hasParameterOverlap = true;
                continue;
            }

            // If both are identical literals, continue
            if ($seg1 === $seg2) {
                continue;
            }

            // If one is parameter and other is literal, potential conflict only if other segments match
            if ($this->isParameter($seg1) || $this->isParameter($seg2)) {
                $hasParameterOverlap = true;
                continue;
            }

            // Different literals, no conflict
            return false;
        }

        // Only conflict if there was parameter overlap and other segments matched
        return $hasParameterOverlap;
    }

    /**
     * Check if a route segment is a parameter
     */
    protected function isParameter(string $segment): bool
    {
        return strpos($segment, '{') === 0 && strpos($segment, '}') === strlen($segment) - 1;
    }

    /**
     * Log route conflicts
     */
    protected function logRouteConflicts(): void
    {
        if (function_exists('logger')) {
            logger()->warning('Auto CRUD route conflicts detected', [
                'conflicts' => $this->conflictLog,
                'package' => 'laravel-auto-crud'
            ]);
        }

        // Also log to Laravel log if in console mode
        if (app()->runningInConsole()) {
            echo "\n⚠️  Route Conflicts Detected:\n";
            foreach ($this->conflictLog as $conflict) {
                echo "  - {$conflict['model']}::{$conflict['method']} -> {$conflict['http_method']} {$conflict['route_pattern']} (Reason: {$conflict['reason']})\n";
            }
            echo "\n";
        }
    }

    // ========================================
    // PUBLIC API - Route Metadata Management
    // ========================================

    /**
     * Get metadata for all generated routes
     */
    public function getGeneratedRoutesMetadata(): array
    {
        return Cache::get($this->metadataCacheKey, []);
    }

    /**
     * Get metadata for routes of specific models
     */
    public function getModelRoutesMetadata(array $modelClasses): array
    {
        $metadata = Cache::get($this->metadataCacheKey, []);

        return array_filter($metadata, function ($routeData) use ($modelClasses) {
            return in_array($routeData['model'], $modelClasses);
        });
    }

    /**
     * Check if any routes are currently generated
     */
    public function hasGeneratedRoutes(): bool
    {
        $metadata = Cache::get($this->metadataCacheKey, []);
        return !empty($metadata);
    }

    /**
     * Get count of generated routes by model
     */
    public function getGeneratedRoutesCount(): array
    {
        $metadata = Cache::get($this->metadataCacheKey, []);
        $counts = [];

        foreach ($metadata as $routeData) {
            $model = $routeData['model'];
            $counts[$model] = ($counts[$model] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * Validate if generated routes still exist in router
     */
    public function validateGeneratedRoutes(): array
    {
        $metadata = Cache::get($this->metadataCacheKey, []);
        $router = app('router');
        $routes = $router->getRoutes();
        $issues = [];

        foreach ($metadata as $routeName => $routeData) {
            if (!$routes->hasNamedRoute($routeName)) {
                $issues[] = [
                    'route_name' => $routeName,
                    'model' => $routeData['model'],
                    'issue' => 'Route no longer exists in router',
                    'metadata' => $routeData
                ];
            }
        }

        return $issues;
    }

    /**
     * Clean up stale metadata (routes that no longer exist)
     */
    public function cleanupStaleMetadata(): int
    {
        $metadata = Cache::get($this->metadataCacheKey, []);
        $router = app('router');
        $routes = $router->getRoutes();
        $cleaned = 0;

        foreach ($metadata as $routeName => $routeData) {
            if (!$routes->hasNamedRoute($routeName)) {
                unset($metadata[$routeName]);
                $cleaned++;
            }
        }

        if ($cleaned > 0) {
            Cache::put($this->metadataCacheKey, $metadata, now()->addDays(30));
        }

        return $cleaned;
    }

    // ========================================
    // PUBLIC API - Route Reset Management
    // ========================================

    /**
     * Reset/remove all generated routes
     * Note: Laravel doesn't support removing routes after registration.
     * This method clears metadata and logs a warning.
     */
    public function resetGeneratedRoutes(): bool
    {
        try {
            $metadata = Cache::get($this->metadataCacheKey, []);

            if (empty($metadata)) {
                return true; // Nothing to reset
            }

            // Laravel doesn't support removing routes after they're registered
            // We can only clear the metadata and warn the user
            $this->logRouteResetWarning(array_keys($metadata));

            // Clear metadata
            Cache::forget($this->metadataCacheKey);

            return true;
        } catch (\Exception $e) {
            if (function_exists('logger')) {
                logger()->error('Failed to reset auto-crud routes', [
                    'error' => $e->getMessage(),
                    'package' => 'laravel-auto-crud'
                ]);
            }
            return false;
        }
    }

    /**
     * Reset routes for specific models
     * Note: Laravel doesn't support removing routes after registration.
     * This method clears metadata and logs a warning.
     */
    public function resetRoutesForModels(array $modelClasses): bool
    {
        try {
            $metadata = Cache::get($this->metadataCacheKey, []);

            if (empty($metadata)) {
                return true; // Nothing to reset
            }

            $updatedMetadata = $metadata;
            $removedRoutes = [];

            // Find routes for specified models
            foreach ($metadata as $routeName => $routeData) {
                if (in_array($routeData['model'], $modelClasses)) {
                    $removedRoutes[] = $routeName;
                    unset($updatedMetadata[$routeName]);
                }
            }

            if (!empty($removedRoutes)) {
                $this->logRouteResetWarning($removedRoutes);
            }

            // Update metadata
            Cache::put($this->metadataCacheKey, $updatedMetadata, now()->addDays(30));

            return true;
        } catch (\Exception $e) {
            if (function_exists('logger')) {
                logger()->error('Failed to reset routes for specific models', [
                    'models' => $modelClasses,
                    'error' => $e->getMessage(),
                    'package' => 'laravel-auto-crud'
                ]);
            }
            return false;
        }
    }

    /**
     * Log warning about route reset limitations
     */
    private function logRouteResetWarning(array $routeNames): void
    {
        $message = 'Auto-CRUD route metadata cleared, but routes remain active until next request cycle. ' .
                  'Consider restarting your application or clearing route cache.';

        if (function_exists('logger')) {
            logger()->warning($message, [
                'routes_cleared' => $routeNames,
                'package' => 'laravel-auto-crud'
            ]);
        }

        if (app()->runningInConsole()) {
            echo "\n⚠️  Route Reset Limitation:\n";
            echo "   Laravel doesn't support removing routes after registration.\n";
            echo "   Metadata cleared for " . count($routeNames) . " routes, but they remain active.\n";
            echo "   Consider running: php artisan route:clear && php artisan route:cache\n\n";
        }
    }

    /**
     * Check if routes should be regenerated based on configuration changes
     */
    public function shouldRegenerateRoutes(): bool
    {
        if (!($this->config['auto_reset_on_config_change'] ?? true)) {
            return false;
        }

        $currentConfigHash = $this->getConfigHash();
        $cachedConfigHash = Cache::get('auto_crud_config_hash', null);

        return $currentConfigHash !== $cachedConfigHash;
    }

    /**
     * Store current configuration hash for change detection
     */
    public function storeConfigHash(): void
    {
        $configHash = $this->getConfigHash();
        Cache::put('auto_crud_config_hash', $configHash, now()->addDays(30));
    }

    /**
     * Get hash of current configuration for change detection
     */
    private function getConfigHash(): string
    {
        $relevantConfig = [
            'models' => $this->config['models'] ?? [],
            'crud_methods' => $this->config['crud_methods'] ?? [],
            'route_prefix' => $this->config['route_prefix'] ?? 'api',
            'route_namespace' => $this->config['route_namespace'] ?? '',
            'middleware' => $this->config['middleware'] ?? [],
            'default_controller' => $this->config['default_controller'] ?? '',
        ];

        return md5(serialize($relevantConfig));
    }

    /**
     * Prevent route generation if routes already exist and conflicts are disabled
     */
    private function shouldSkipGeneration(): bool
    {
        // If we don't prevent conflicts, always generate
        if (!$this->preventConflicts) {
            return false;
        }

        // If no routes are currently generated, don't skip
        if (!$this->hasGeneratedRoutes()) {
            return false;
        }

        // If configuration hasn't changed, skip generation
        if (!$this->shouldRegenerateRoutes()) {
            return true;
        }

        return false;
    }
}
