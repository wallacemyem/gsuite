<?php

namespace Wyattcast44\GSuite;

use Illuminate\Support\ServiceProvider;
use Wyattcast44\GSuite\Clients\GoogleClient;
use Wyattcast44\GSuite\Resources\Groups\GSuiteGroup;
use Wyattcast44\GSuite\Clients\GoogleServicesClient;
use Wyattcast44\GSuite\Resources\Accounts\GSuiteAccount;
use Wyattcast44\GSuite\Resources\Groups\GroupsRepository;
use Wyattcast44\GSuite\Resources\Accounts\AccountsRepository;

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
        ], 'gsuite');
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/gsuite.php', 'gsuite');
    
        $this->app->singleton('google-client', function () {
            return new GoogleClient;
        });

        $this->app->singleton('google-services', function () {
            return new GoogleServicesClient(app('google-client'));
        });

        $this->app->singleton('gsuite-accounts-repo', function () {
            return new AccountsRepository(app('google-services'));
        });

        $this->app->singleton('gsuite-account', function () {
            return new GSuiteAccount(app('gsuite-accounts-repo'));
        });

        $this->app->singleton('gsuite-groups-repo', function () {
            return new GroupsRepository(app('google-services'));
        });

        $this->app->singleton('gsuite-group', function () {
            return new GSuiteGroup(app('gsuite-groups-repo'));
        });

        $this->app->singleton('gsuite', function () {
            return new GSuite(app('gsuite-groups-repo'), app('gsuite-accounts-repo'));
        });
    }
}
