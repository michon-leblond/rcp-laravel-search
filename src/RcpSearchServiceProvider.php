<?php

namespace Rcp\LaravelSearch;

use Illuminate\Support\ServiceProvider;

class RcpSearchServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Merge configuration
        $this->mergeConfigFrom(
            __DIR__.'/../config/rcp-search.php',
            'rcp-search'
        );

        // Register SearchController
        $this->app->bind('rcp.search.controller', function ($app) {
            return new \Rcp\LaravelSearch\Controllers\SearchController();
        });

        // Register SearchHelper
        $this->app->bind('rcp.search.helper', function ($app) {
            return new \Rcp\LaravelSearch\Helpers\SearchHelper();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish configuration
        $this->publishes([
            __DIR__.'/../config/rcp-search.php' => config_path('rcp-search.php'),
        ], 'rcp-search-config');

        // Publish views
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/rcp-search'),
        ], 'rcp-search-views');

        // Load views
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'rcp-search');
    }
}