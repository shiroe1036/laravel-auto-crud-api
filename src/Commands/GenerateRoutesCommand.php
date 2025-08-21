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
            $this->warn('No models configured in auto-crud.models config.');
            $this->info('You can run with --scan to automatically discover models.');
            return 0;
        }

        $this->info("📋 Generating routes from configuration...");
        $this->info("Found " . count($models) . " configured model(s):");

        foreach ($models as $modelClass => $config) {
            $this->line("  - {$modelClass}");
        }
        $this->newLine();

        if ($this->option('dry-run')) {
            foreach ($models as $modelClass => $config) {
                $routeInfo = $this->routeGenerator->getModelRouteInfo($modelClass, $config);
                $this->displayRouteInfo($routeInfo);
                $this->newLine();
            }
        } else {
            $this->routeGenerator->generateRoutes();
            $this->info("✅ Routes generated for all configured models");
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
}
