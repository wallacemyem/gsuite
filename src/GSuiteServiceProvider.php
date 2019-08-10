<?php

namespace Wyattcast44\GSuite;

// Commands
use Wyattcast44\GSuite\Commands\CreateAccount;
use Wyattcast44\GSuite\Commands\DeleteAccount;
use Wyattcast44\GSuite\Commands\SuspendAccount;
use Wyattcast44\GSuite\Commands\UnsuspendAccount;

// Clients
use Wyattcast44\GSuite\Clients\GoogleClient;
use Wyattcast44\GSuite\Clients\GoogleServicesClient;

// Repos
use Wyattcast44\GSuite\Resources\Groups\GroupsRepository;
use Wyattcast44\GSuite\Resources\Accounts\AccountsRepository;

// Misc
use Illuminate\Support\ServiceProvider;
use Wyattcast44\GSuite\Resources\Groups\GSuiteGroup;
use Wyattcast44\GSuite\Resources\Accounts\GSuiteAccount;
use Wyattcast44\GSuite\Resources\Accounts\GSuiteAccounts;

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
    
        // The base Google client
        $this->app->singleton('google-client', function () {
            return new GoogleClient;
        });

        // The Google service resolver
        $this->app->singleton('google-services', function () {
            return new GoogleServicesClient(app('google-client'));
        });

        // The base GSuite class
        $this->app->singleton('gsuite', function () {
            return new GSuite(app('gsuite-groups-repo'), app('gsuite-accounts'));
        });

        // G-Suite accounts repo
        $this->app->singleton('gsuite-accounts-repo', function () {
            return new AccountsRepository(app('google-services'));
        });
        
        // G-Suite accounts
        $this->app->singleton('gsuite-accounts', function () {
            return new GSuiteAccounts(app('gsuite-accounts-repo'));
        });
        
        // Single G-Suite account
        $this->app->bind('gsuite-account', function () {
            return new GSuiteAccount(app('gsuite-accounts'));
        });
        
        $this->app->singleton('gsuite-groups-repo', function () {
            return new GroupsRepository(app('google-services'));
        });

        $this->app->singleton('gsuite-group', function () {
            return new GSuiteGroup(app('gsuite-groups-repo'));
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                CreateAccount::class,
                DeleteAccount::class,
                SuspendAccount::class,
                UnsuspendAccount::class,
            ]);
        }
    }
}
