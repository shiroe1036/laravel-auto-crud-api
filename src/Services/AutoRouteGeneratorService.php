<?php

namespace FivoTech\LaravelAutoCrud\Services;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;

class AutoRouteGeneratorService
{
    protected array $config;

    public function __construct()
    {
        $this->config = config('auto-crud', []);
    }

    /**
     * Generate routes for all configured models
     */
    public function generateRoutes(): void
    {
        $models = $this->config['models'] ?? [];

        foreach ($models as $modelClass => $modelConfig) {
            $this->generateRoutesForModel($modelClass, $modelConfig);
        }
    }

    /**
     * Generate routes for a specific model
     */
    public function generateRoutesForModel(string $modelClass, array $modelConfig = []): void
    {
        $controller = $modelConfig['controller'] ?? $this->config['default_controller'];
        $resourceName = $this->getResourceName($modelClass, $modelConfig);
        $middleware = array_merge(
            $this->config['middleware'] ?? [],
            $modelConfig['middleware'] ?? []
        );

        $availableMethods = $this->getAvailableMethods($controller, $modelConfig);

        $routeGroup = Route::prefix($this->config['route_prefix'] ?? 'api')
            ->middleware($middleware);

        $routeGroup->group(function () use ($controller, $resourceName, $availableMethods, $modelClass, $modelConfig) {
            foreach ($availableMethods as $method) {
                $this->generateRouteForMethod($controller, $resourceName, $method, $modelClass, $modelConfig);
            }
        });
    }

    /**
     * Generate a route for a specific method
     */
    protected function generateRouteForMethod(
        string $controller,
        string $resourceName,
        string $method,
        string $modelClass,
        array $modelConfig
    ): void {
        $crudMethods = $this->config['crud_methods'] ?? [];

        if (!isset($crudMethods[$method])) {
            return;
        }

        $methodConfig = $crudMethods[$method];
        $httpMethod = strtolower($methodConfig['http_method']);
        $routePattern = str_replace('{resource}', $resourceName, $methodConfig['route_pattern']);

        $routeName = $this->generateRouteName($resourceName, $method, $modelConfig);

        // Create the route with model binding and hooks support
        $route = Route::{$httpMethod}($routePattern, function (...$args) use ($controller, $method, $modelClass, $modelConfig) {
            $controllerInstance = new $controller();

            if (method_exists($controllerInstance, 'setModel')) {
                $controllerInstance->setModel($modelClass);
            }

            // Apply hooks if configured
            if (isset($modelConfig['hooks'])) {
                $controllerInstance = $this->applyHooksToController($controllerInstance, $modelConfig['hooks']);
            }

            // Apply global hooks
            $globalHooks = $this->config['global_hooks'] ?? [];
            if (!empty($globalHooks)) {
                $controllerInstance = $this->applyHooksToController($controllerInstance, $globalHooks);
            }

            return call_user_func_array([$controllerInstance, $method], $args);
        })->name($routeName);

        // Apply route constraints if defined
        if (isset($methodConfig['where']) && is_array($methodConfig['where'])) {
            $route->where($methodConfig['where']);
        }
    }

    /**
     * Apply hooks to controller instance
     */
    protected function applyHooksToController($controllerInstance, array $hooks)
    {
        if (method_exists($controllerInstance, '__construct')) {
            // If the controller supports hooks in constructor, pass them
            $reflection = new \ReflectionClass($controllerInstance);
            $constructor = $reflection->getConstructor();

            if ($constructor) {
                $parameters = $constructor->getParameters();
                foreach ($parameters as $parameter) {
                    if ($parameter->getName() === 'options' && isset($hooks)) {
                        // Recreate instance with hooks
                        $model = $controllerInstance->model ?? null;
                        return new ($reflection->getName())($model, ['hooks' => $hooks]);
                    }
                }
            }
        }

        return $controllerInstance;
    }

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
     * Scan for models in the application
     */
    public function scanForModels(string $directory = null): array
    {
        $directory = $directory ?? app_path('Models');
        $models = [];

        if (!is_dir($directory)) {
            return $models;
        }

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

    /**
     * Get route information for a model
     */
    public function getModelRouteInfo(string $modelClass, array $modelConfig = []): array
    {
        $controller = $modelConfig['controller'] ?? $this->config['default_controller'];
        $resourceName = $this->getResourceName($modelClass, $modelConfig);
        $availableMethods = $this->getAvailableMethods($controller, $modelConfig);
        $routes = [];

        foreach ($availableMethods as $method) {
            $crudMethods = $this->config['crud_methods'] ?? [];
            if (isset($crudMethods[$method])) {
                $methodConfig = $crudMethods[$method];
                $routePattern = str_replace('{resource}', $resourceName, $methodConfig['route_pattern']);
                $routeName = $this->generateRouteName($resourceName, $method, $modelConfig);

                $routes[] = [
                    'method' => $method,
                    'http_method' => $methodConfig['http_method'],
                    'pattern' => $routePattern,
                    'name' => $routeName,
                    'controller' => $controller,
                ];
            }
        }

        return [
            'model' => $modelClass,
            'resource_name' => $resourceName,
            'controller' => $controller,
            'routes' => $routes,
        ];
    }
}
