<?php

namespace FivoTech\LaravelAutoCrud\Providers;

use Illuminate\Support\ServiceProvider;
use FivoTech\LaravelAutoCrud\Commands\GenerateRoutesCommand;
use FivoTech\LaravelAutoCrud\Services\AutoRouteGeneratorService;
use FivoTech\LaravelAutoCrud\Services\GenericQueryBuilderService;

class AutoCrudServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        // Publish configuration file
        $this->publishes([
            __DIR__ . '/../../config/auto-crud.php' => config_path('auto-crud.php'),
        ], 'auto-crud-config');

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateRoutesCommand::class,
            ]);
        }

        // Auto-generate routes if enabled in config
        if (config('auto-crud.auto_generate_routes', false)) {
            $this->generateRoutes();
        }
    }

    /**
     * Register any package services.
     */
    public function register(): void
    {
        // Merge package configuration
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/auto-crud.php',
            'auto-crud'
        );

        // Register services
        $this->app->singleton(GenericQueryBuilderService::class);
        $this->app->singleton(AutoRouteGeneratorService::class);

        // Register facade
        $this->app->singleton('auto-crud', function ($app) {
            return $app->make(AutoRouteGeneratorService::class);
        });
    }

    /**
     * Generate routes automatically based on configuration
     */
    protected function generateRoutes(): void
    {
        $routeGenerator = $this->app->make(AutoRouteGeneratorService::class);
        $routeGenerator->generateRoutes();
    }
}
