<?php

namespace FivoTech\LaravelAutoCrud\Commands;

use Illuminate\Console\Command;
use FivoTech\LaravelAutoCrud\Services\AutoRouteGeneratorService;

class ResetRoutesCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'auto-crud:reset-routes
                            {--all : Reset all auto-generated routes}
                            {--models= : Comma-separated list of model classes to reset routes for}
                            {--force : Skip confirmation prompts}
                            {--cleanup : Clean up stale metadata}
                            {--show : Show current route metadata without resetting}';

    /**
     * The console command description.
     */
    protected $description = 'Reset/remove auto-generated CRUD routes';

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
        $this->info('🔄 Auto CRUD Route Reset Tool');
        $this->newLine();

        if ($this->option('show')) {
            return $this->showRouteMetadata();
        }

        if ($this->option('cleanup')) {
            return $this->cleanupStaleMetadata();
        }

        if ($this->option('models')) {
            return $this->resetSpecificModels();
        }

        if ($this->option('all')) {
            return $this->resetAllRoutes();
        }

        // Default: show options
        $this->displayUsageInfo();
        return 0;
    }

    /**
     * Reset all auto-generated routes
     */
    protected function resetAllRoutes(): int
    {
        if (!$this->routeGenerator->hasGeneratedRoutes()) {
            $this->info('✅ No auto-generated routes found to reset.');
            return 0;
        }

        $metadata = $this->routeGenerator->getGeneratedRoutesMetadata();
        $this->displayRoutesSummary($metadata);

        if (!$this->option('force') && !$this->confirm('Are you sure you want to reset ALL auto-generated routes?')) {
            $this->info('Operation cancelled.');
            return 0;
        }

        $this->info('🔄 Resetting all auto-generated routes...');

        if ($this->routeGenerator->resetGeneratedRoutes()) {
            $this->info('✅ All auto-generated routes have been reset successfully.');
            return 0;
        } else {
            $this->error('❌ Failed to reset routes. Check logs for details.');
            return 1;
        }
    }

    /**
     * Reset routes for specific models
     */
    protected function resetSpecificModels(): int
    {
        $modelsInput = $this->option('models');
        $modelClasses = array_map('trim', explode(',', $modelsInput));

        // Validate model classes
        $invalidModels = [];
        foreach ($modelClasses as $model) {
            if (!class_exists($model)) {
                $invalidModels[] = $model;
            }
        }

        if (!empty($invalidModels)) {
            $this->error('❌ Invalid model classes: ' . implode(', ', $invalidModels));
            return 1;
        }

        $metadata = $this->routeGenerator->getModelRoutesMetadata($modelClasses);

        if (empty($metadata)) {
            $this->info('✅ No routes found for the specified models.');
            return 0;
        }

        $this->info('Routes to be reset for models: ' . implode(', ', array_map('class_basename', $modelClasses)));
        $this->displayRoutesSummary($metadata);

        if (!$this->option('force') && !$this->confirm('Are you sure you want to reset routes for these models?')) {
            $this->info('Operation cancelled.');
            return 0;
        }

        $this->info('🔄 Resetting routes for specified models...');

        if ($this->routeGenerator->resetRoutesForModels($modelClasses)) {
            $this->info('✅ Routes for specified models have been reset successfully.');
            return 0;
        } else {
            $this->error('❌ Failed to reset routes for specified models. Check logs for details.');
            return 1;
        }
    }

    /**
     * Show current route metadata
     */
    protected function showRouteMetadata(): int
    {
        $metadata = $this->routeGenerator->getGeneratedRoutesMetadata();

        if (empty($metadata)) {
            $this->info('✅ No auto-generated routes found.');
            return 0;
        }

        $this->info('📋 Current Auto-Generated Routes:');
        $this->newLine();

        $this->displayRoutesSummary($metadata, true);

        // Show count by model
        $counts = $this->routeGenerator->getGeneratedRoutesCount();
        $this->newLine();
        $this->info('📊 Routes count by model:');
        foreach ($counts as $model => $count) {
            $this->line("  • " . class_basename($model) . ": {$count} routes");
        }

        return 0;
    }

    /**
     * Clean up stale metadata
     */
    protected function cleanupStaleMetadata(): int
    {
        $this->info('🧹 Cleaning up stale route metadata...');

        $cleaned = $this->routeGenerator->cleanupStaleMetadata();

        if ($cleaned > 0) {
            $this->info("✅ Cleaned up {$cleaned} stale route entries.");
        } else {
            $this->info('✅ No stale metadata found.');
        }

        return 0;
    }

    /**
     * Display routes summary table
     */
    protected function displayRoutesSummary(array $metadata, bool $detailed = false): void
    {
        if (empty($metadata)) {
            return;
        }

        $headers = ['Route Name', 'Model', 'Method', 'HTTP', 'Pattern'];
        if ($detailed) {
            $headers[] = 'Generated At';
        }

        $rows = [];
        foreach ($metadata as $routeName => $routeData) {
            $row = [
                $routeName,
                class_basename($routeData['model']),
                $routeData['method'],
                $routeData['http_method'],
                $routeData['pattern']
            ];

            if ($detailed) {
                $row[] = \Carbon\Carbon::parse($routeData['generated_at'])->format('Y-m-d H:i:s');
            }

            $rows[] = $row;
        }

        $this->table($headers, $rows);
    }

    /**
     * Display usage information
     */
    protected function displayUsageInfo(): void
    {
        $this->info('Available options:');
        $this->newLine();
        $this->line('  <fg=green>--show</fg=green>                 Show current auto-generated routes');
        $this->line('  <fg=green>--all</fg=green>                  Reset all auto-generated routes');
        $this->line('  <fg=green>--models=Model1,Model2</fg=green>  Reset routes for specific models');
        $this->line('  <fg=green>--cleanup</fg=green>               Clean up stale metadata');
        $this->line('  <fg=green>--force</fg=green>                 Skip confirmation prompts');
        $this->newLine();
        $this->info('Examples:');
        $this->line('  <fg=yellow>php artisan auto-crud:reset-routes --show</fg=yellow>');
        $this->line('  <fg=yellow>php artisan auto-crud:reset-routes --all</fg=yellow>');
        $this->line('  <fg=yellow>php artisan auto-crud:reset-routes --models="App\\Models\\User,App\\Models\\Post"</fg=yellow>');
        $this->line('  <fg=yellow>php artisan auto-crud:reset-routes --cleanup</fg=yellow>');
    }
}
