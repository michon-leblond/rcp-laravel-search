<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how search parameters are cached
    |
    */
    
    'cache' => [
        'ttl' => 60, // Time to live in minutes
        'prefix' => 'search_', // Cache key prefix
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Search Configuration
    |--------------------------------------------------------------------------
    |
    | Default configuration that can be overridden by individual controllers
    |
    */
    
    'defaults' => [
        'filters' => [],
        'sorts' => [],
        'pagination' => 15,
    ],

];