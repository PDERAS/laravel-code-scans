<?php

namespace Pderas\LaravelCodeScans;

use Illuminate\Support\ServiceProvider;

class LaravelCodeScansServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/laravel-code-scans.php',
            'laravel-code-scans'
        );
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            // Publish config file
            $this->publishes([
                __DIR__ . '/../config/laravel-code-scans.php' => config_path('laravel-code-scans.php'),
            ]);

            // Register artisan command
            $this->commands([
                \Pderas\LaravelCodeScans\Console\Commands\CodeScanCommand::class,
            ]);
        }
    }
}
