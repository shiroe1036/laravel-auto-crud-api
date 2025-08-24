<?php

namespace FivoTech\LaravelAutoCrud\Commands;

use Illuminate\Console\Command;
use FivoTech\LaravelAutoCrud\Services\AutoRouteGeneratorService;

class GenerateRoutesCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'auto-crud:generate-routes
                            {--scan : Scan for models automatically}
                            {--model= : Generate routes for specific model}
                            {--directory= : Directory to scan for models}
                            {--validate : Validate routes without generating them}
                            {--reset : Reset existing routes before generating new ones}
                            {--dry-run : Show what routes would be generated without actually generating them}';

    /**
     * The console command description.
     */
    protected $description = 'Generate CRUD routes automatically for models';

    protected AutoRouteGeneratorService $routeGenerator;

    public function __construct(AutoRouteGeneratorService $routeGenerator)
    {
        parent::__construct();
        $this->routeGenerator = $routeGenerator;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚀 Auto CRUD Route Generator');
        $this->newLine();

        if ($this->option('validate')) {
            return $this->validateRoutes();
        }

        if ($this->option('model')) {
            return $this->generateForSpecificModel();
        }

        if ($this->option('scan')) {
            return $this->scanAndGenerate();
        }

        return $this->generateFromConfig();
    }

    /**
     * Generate routes for a specific model
     */
    protected function generateForSpecificModel(): int
    {
        $modelClass = $this->option('model');

        if (!class_exists($modelClass)) {
            $this->error("Model class {$modelClass} does not exist.");
            return 1;
        }

        $routeInfo = $this->routeGenerator->getModelRouteInfo($modelClass);

        if ($this->option('dry-run')) {
            $this->displayRouteInfo($routeInfo);
        } else {
            $this->routeGenerator->generateRoutesForModel($modelClass);
            $this->info("✅ Routes generated for model: {$modelClass}");
        }

        return 0;
    }

    /**
     * Scan for models and generate routes
     */
    protected function scanAndGenerate(): int
    {
        $directory = $this->option('directory') ?? app_path('Models');

        $this->info("🔍 Scanning for models in: {$directory}");

        $models = $this->routeGenerator->scanForModels($directory);

        if (empty($models)) {
            $this->warn('No Eloquent models found.');
            return 0;
        }

        $this->info("Found " . count($models) . " model(s):");
        foreach ($models as $model) {
            $this->line("  - {$model}");
        }
        $this->newLine();

        if ($this->option('dry-run')) {
            foreach ($models as $model) {
                $routeInfo = $this->routeGenerator->getModelRouteInfo($model);
                $this->displayRouteInfo($routeInfo);
                $this->newLine();
            }
        } else {
            $generatedModels = $this->routeGenerator->generateRoutesForDiscoveredModels($directory);
            $this->info("✅ Routes generated for " . count($generatedModels) . " model(s)");
        }

        return 0;
    }

    /**
     * Generate routes from configuration
     */
    protected function generateFromConfig(): int
    {
        $models = config('auto-crud.models', []);

        if (empty($models)) {
            $this->warn('No models configured for auto-generation.');
            $this->info('Add models to your config/auto-crud.php file or use --scan option.');
            return 1;
        }

        // Reset existing routes if requested
        if ($this->option('reset')) {
            $this->info('🔄 Resetting existing routes...');
            if (!$this->routeGenerator->resetGeneratedRoutes()) {
                $this->error('Failed to reset existing routes.');
                return 1;
            }
            $this->info('✅ Existing routes reset successfully.');
            $this->newLine();
        }

        if ($this->option('dry-run')) {
            $this->info('🔍 Dry run mode - showing routes that would be generated:');
            $this->newLine();

            foreach ($models as $modelClass => $modelConfig) {
                $routeInfo = $this->routeGenerator->getModelRouteInfo($modelClass, $modelConfig);
                $this->line("📁 Model: <fg=cyan>{$modelClass}</fg=cyan>");
                $this->displayRouteInfo($routeInfo);
                $this->newLine();
            }

            return 0;
        }

        $this->info('🚀 Generating routes for configured models...');

        $generated = 0;
        foreach ($models as $modelClass => $modelConfig) {
            if (class_exists($modelClass)) {
                $this->routeGenerator->generateRoutesForModel($modelClass, $modelConfig);
                $this->line("✅ Routes generated for: <fg=green>{$modelClass}</fg=green>");
                $generated++;
            } else {
                $this->error("❌ Model class not found: {$modelClass}");
            }
        }

        $this->newLine();
        $this->info("✅ Route generation complete. Generated routes for {$generated} models.");

        // Show conflicts if any
        $conflicts = $this->routeGenerator->getConflicts();
        if (!empty($conflicts)) {
            $this->newLine();
            $this->warn('⚠️  Some routes were skipped due to conflicts:');
            foreach ($conflicts as $conflict) {
                $this->line("  - {$conflict['model']}::{$conflict['method']} ({$conflict['reason']})");
            }
        }

        return 0;
    }
    /**
     * Display route information
     */
    protected function displayRouteInfo(array $routeInfo): void
    {
        $this->info("Model: {$routeInfo['model']}");
        $this->info("Resource: {$routeInfo['resource_name']}");
        $this->info("Controller: {$routeInfo['controller']}");
        $this->newLine();

        if (empty($routeInfo['routes'])) {
            $this->warn('No routes would be generated for this model.');
            return;
        }

        $headers = ['Method', 'HTTP', 'Pattern', 'Name'];
        $rows = [];

        foreach ($routeInfo['routes'] as $route) {
            $rows[] = [
                $route['method'],
                $route['http_method'],
                $route['pattern'],
                $route['name'],
            ];
        }

        $this->table($headers, $rows);
    }

    /**
     * Validate routes for conflicts
     */
    protected function validateRoutes(): int
    {
        $this->info('🔍 Validating routes for conflicts...');
        $this->newLine();

        $conflicts = $this->routeGenerator->validateRoutes();

        if (empty($conflicts)) {
            $this->info('✅ No route conflicts detected. All routes can be safely generated.');
            return 0;
        }

        $this->error('⚠️  Route conflicts detected:');
        $this->newLine();

        $headers = ['Model', 'Method', 'HTTP', 'Pattern', 'Name', 'Reason'];
        $rows = [];

        foreach ($conflicts as $conflict) {
            $rows[] = [
                class_basename($conflict['model']),
                $conflict['method'],
                $conflict['http_method'],
                $conflict['route_pattern'],
                $conflict['route_name'],
                $conflict['reason'],
            ];
        }

        $this->table($headers, $rows);
        $this->newLine();
        $this->warn('💡 Consider using route namespacing or disabling auto-generation for conflicting models.');

        return 1;
    }
}
