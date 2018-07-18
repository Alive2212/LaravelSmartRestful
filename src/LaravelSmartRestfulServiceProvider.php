<?php

namespace Alive2212\LaravelSmartRestful;

use Illuminate\Support\ServiceProvider;

class LaravelSmartRestfulServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
         $this->loadTranslationsFrom(resource_path('lang/vendor/alive2212'), 'laravel_smart_restful');
        // $this->loadViewsFrom(__DIR__.'/../resources/views', 'alive2212');
//        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
//        $this->loadRoutesFrom(__DIR__.'/routes.php');

        // Publishing is only necessary when using the CLI.
        if ($this->app->runningInConsole()) {

            // Publishing the configuration file.
//            $this->publishes([
//                __DIR__.'/../config/laravelwalletservice.php' => config_path('laravelwalletservice.php'),
//            ], 'laravelwalletservice.config');

            // Publishing the views.
            /*$this->publishes([
                __DIR__.'/../resources/views' => base_path('resources/views/vendor/alive2212'),
            ], 'laravelwalletservice.views');*/

            // Publishing assets.
            /*$this->publishes([
                __DIR__.'/../resources/assets' => public_path('vendor/alive2212'),
            ], 'laravelwalletservice.views');*/

            // Publishing the translation files.
            $this->publishes([
                __DIR__.'/../resources/lang/' => resource_path('lang/vendor/alive2212'),
            ], 'laravel_smart_restful.lang');

            // Registering package commands.
            // $this->commands([]);
        }
    }

    /**
     * Register any package services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}