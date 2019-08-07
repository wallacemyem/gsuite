<?php

namespace Wyattcast44\GSuite;

use Illuminate\Support\ServiceProvider;
use Wyattcast44\GSuite\Accounts\GSuiteAccount;

class GSuiteServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        // Publish config file...
        $this->publishes([
            __DIR__ . '/../config/gsuite.php' => config_path('gsuite.php'),
        ], 'config');
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/gsuite.php', 'gsuite');

        $this->app->singleton('gsuite', function () {
            return new GSuite;
        });

        $this->app->singleton(GSuiteDirectory::class, function () {
            return new GSuiteDirectory;
        });

        $this->app->singleton(GSuiteAccount::class, function () {
            return new GSuiteAccount;
        });
    }
}
