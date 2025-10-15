<?php

namespace Rcp\LaravelSearch\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;

class SearchController
{
    /**
     * Store search parameters
     */
    public function store(Request $request)
    {
        $searchData = $request->all();
        $cacheKey = $this->getCacheKey($request);
        
        Cache::put($cacheKey, $searchData, now()->addMinutes(60));
        
        return response()->json(['success' => true, 'data' => $searchData]);
    }

    /**
     * Get search parameters with proper request merging
     */
    public function get(Request $request, array $defaults = [])
    {
        $cacheKey = $this->getCacheKey($request);
        
        // Get request parameters
        $requestData = $request->all();
        
        // Get cached data
        $cachedData = Cache::get($cacheKey, []);
        
        // Priority: Request parameters > Cached data > Defaults
        $searchData = array_merge($defaults, $cachedData, array_filter($requestData, function($value) {
            return $value !== null && $value !== '';
        }));
        
        // Update cache with merged data
        Cache::put($cacheKey, $searchData, now()->addMinutes(60));
        
        return $searchData;
    }

    /**
     * Clear search parameters
     */
    public function clear(Request $request)
    {
        $cacheKey = $this->getCacheKey($request);
        Cache::forget($cacheKey);
        
        return response()->json(['success' => true]);
    }

    /**
     * Update specific search parameter
     */
    public function update(Request $request)
    {
        $cacheKey = $this->getCacheKey($request);
        $existingData = Cache::get($cacheKey, []);
        $newData = array_merge($existingData, $request->all());
        
        Cache::put($cacheKey, $newData, now()->addMinutes(60));
        
        return response()->json(['success' => true, 'data' => $newData]);
    }

    /**
     * Store default values for a route
     */
    public function storeDefaults(Request $request, array $defaults)
    {
        $cacheKey = $this->getCacheKey($request);
        $existingData = Cache::get($cacheKey, []);
        
        // Only set defaults for keys that don't exist
        foreach ($defaults as $key => $value) {
            if (!isset($existingData[$key]) || empty($existingData[$key])) {
                $existingData[$key] = $value;
            }
        }
        
        Cache::put($cacheKey, $existingData, now()->addMinutes(60));
        
        return $existingData;
    }

    /**
     * Generate cache key based on route and user
     */
    private function getCacheKey(Request $request): string
    {
        $route = $request->route()->getName() ?? $request->path();
        $userId = Auth::id() ?? 'guest';
        
        return "search_{$userId}_{$route}";
    }
}
