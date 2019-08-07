<?php

namespace Wyattcast44\GSuite;

use GSuiteAccount;
use Illuminate\Support\ServiceProvider;

class GSuiteServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/gsuite.php' => config_path('gsuite.php'),
            ], 'config');
        }
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/gsuite.php', 'gsuite');

        $this->app->singleton(GSuite::class, function () {
            return new GSuite;
        });

        $this->app->singleton(GSuiteDirectory::class, function () {
            return new GSuiteDirectory;
        });

        $this->app->singleton(GSuiteAccount::class, function () {
            return new GSuiteAccount;
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [GSuite::class, GSuiteAccount::class];
    }
}
