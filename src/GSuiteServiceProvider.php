<?php

namespace Wyattcast44\GSuite;

use Illuminate\Support\ServiceProvider;
use Wyattcast44\GSuite\Accounts\GSuiteAccount;
use Wyattcast44\GSuite\Accounts\GSuiteAccountsRepository;

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

        $this->app->singleton('gsuite-directory', function () {
            return new GSuiteDirectory(app('gsuite'));
        });

        $this->app->singleton('gsuite-accounts-repo', function () {
            return new GSuiteAccountsRepository(app('gsuite-directory'));
        });

        $this->app->singleton('gsuite-account', function () {
            return new GSuiteAccount(app('gsuite-accounts-repo'));
        });
    }
}
