<?php

namespace Rcp\LaravelSearch\Traits;

use Illuminate\Http\Request;
use Rcp\LaravelSearch\Controllers\SearchController;

trait Searchable
{
    /**
     * Handle search with defaults and return filtered query
     */
    protected function handleSearch(Request $request, $query, array $defaults = [])
    {
        $searchController = new SearchController();
        
        // Store defaults if provided
        if (!empty($defaults)) {
            $searchController->storeDefaults($request, $defaults);
        }
        
        // Get search data (with defaults applied)
        $searchData = $searchController->get($request, $defaults);
        
        // Get search configuration
        $config = method_exists($this, 'getSearchConfiguration') 
            ? $this->getSearchConfiguration() 
            : [];
        
        // Apply search filters
        $query = $this->applySearchFilters($query, $searchData, $config);
        
        // Apply sorting
        $query = $this->applySorting($query, $searchData, $config);
        
        return $query;
    }

    /**
     * Apply search filters to the query
     */
    protected function applySearchFilters($query, array $searchData, array $config = [])
    {
        $filters = $config['filters'] ?? [];
        
        foreach ($filters as $field => $filterConfig) {
            if (!isset($searchData[$field]) || empty($searchData[$field])) {
                continue;
            }
            
            $value = $searchData[$field];
            $type = is_array($filterConfig) ? ($filterConfig['type'] ?? 'text') : $filterConfig;
            
            switch ($type) {
                case 'date':
                    $this->applyDateFilters($query, $field, $value, $filterConfig);
                    break;
                    
                case 'relation':
                    $relation = $filterConfig['relation'] ?? $field;
                    $relationField = $filterConfig['field'] ?? 'id';
                    $query->whereHas($relation, function($q) use ($relationField, $value) {
                        $q->where($relationField, $value);
                    });
                    break;
                    
                case 'text':
                    $query->where($field, 'LIKE', "%{$value}%");
                    break;
                    
                case 'exact':
                    $query->where($field, $value);
                    break;
                    
                case 'custom':
                    $callback = $filterConfig['callback'] ?? null;
                    if ($callback && is_callable($callback)) {
                        $callback($query, $value, $searchData);
                    }
                    break;
                    
                default:
                    $query->where($field, $value);
                    break;
            }
        }
        
        return $query;
    }

    /**
     * Apply date filters (year, month, specific date)
     */
    protected function applyDateFilters($query, $baseField, $value, $config)
    {
        if (isset($config['year_field'])) {
            $yearField = $config['year_field'];
            if (isset($value)) {
                $query->whereYear($yearField, $value);
            }
        }
        
        if (isset($config['month_field'])) {
            $monthField = $config['month_field'];
            if (isset($value)) {
                $query->whereMonth($monthField, $value);
            }
        }
        
        if (isset($config['date_field'])) {
            $dateField = $config['date_field'];
            if (isset($value)) {
                $query->whereDate($dateField, $value);
            }
        }
        
        // Default behavior for simple date field
        if (!isset($config['year_field']) && !isset($config['month_field']) && !isset($config['date_field'])) {
            $query->whereDate($baseField, $value);
        }
    }

    /**
     * Apply sorting to the query
     */
    protected function applySorting($query, array $searchData, array $config = [])
    {
        $sortField = $searchData['sort'] ?? null;
        $sortDirection = $searchData['direction'] ?? 'asc';
        
        if ($sortField) {
            $sorts = $config['sorts'] ?? [];
            
            if (isset($sorts[$sortField])) {
                $sortConfig = $sorts[$sortField];
                
                if (is_array($sortConfig) && isset($sortConfig['callback'])) {
                    // Custom sort callback
                    $callback = $sortConfig['callback'];
                    if (is_callable($callback)) {
                        $callback($query, $sortDirection, $searchData);
                    }
                } else {
                    // Simple field sort
                    $actualField = is_array($sortConfig) ? $sortConfig['field'] : $sortConfig;
                    $query->orderBy($actualField, $sortDirection);
                }
            } else {
                // Default sort by field name
                $query->orderBy($sortField, $sortDirection);
            }
        }
        
        return $query;
    }
}