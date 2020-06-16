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
        $this->loadTranslationsFrom(resource_path('lang/vendor/alive2212'),
            'laravel-smart-restful');

        // Publishing is only necessary when using the CLI.
        if ($this->app->runningInConsole()) {

            // Publishing the translation files.
            $this->publishes([
                __DIR__.'/../resources/lang/' => resource_path('lang/vendor/alive2212'),
            ], 'laravel-smart-restful.lang');

        }
    }

    /**
     * Register any package services.
     *
     * @return void
     */
    public function register()
    {
        // Register the service the package provides.
        $this->app->singleton('laravel-smart-restful', function ($app) {
            return new LaravelSmartRestful;
        });

    }
}