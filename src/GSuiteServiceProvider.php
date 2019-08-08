<?php

namespace Wyattcast44\GSuite;

use Illuminate\Support\ServiceProvider;
use Wyattcast44\GSuite\Clients\GoogleClient;
use Wyattcast44\GSuite\Clients\GoogleServicesClient;
use Wyattcast44\GSuite\Resources\Accounts\AccountsRepo;
use Wyattcast44\GSuite\Resources\Accounts\GSuiteAccount;

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
    
        $this->app->singleton('google-client', function () {
            return new GoogleClient;
        });

        $this->app->singleton('google-services', function () {
            return new GoogleServicesClient(app('google-client'));
        });

        $this->app->bind('gsuite-accounts-repo', function () {
            return new AccountsRepo(app('google-services'));
        });

        // $this->app->singleton('gsuite-account', function () {
        //     return new GSuiteAccount(app('gsuite-accounts-repo'));
        // });
    }
}
