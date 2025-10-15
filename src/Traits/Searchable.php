<?php

namespace Rcp\LaravelSearch\Traits;

use Illuminate\Http\Request;
use Rcp\LaravelSearch\Controllers\SearchController;

trait Searchable
{
    /**
     * Handle search with multiple signature support for backward compatibility
     */
    protected function handleSearch(Request $request, $queryOrRoute = null, array $defaultFilters = [], array $defaultSorting = [])
    {
        $searchController = new SearchController();
        
        // Support for old signature: handleSearch($request, $route, $defaultFilters, $defaultSorting)
        if (is_string($queryOrRoute)) {
            // Store defaults if provided
            if (!empty($defaultFilters)) {
                $searchController->storeDefaults($request, $defaultFilters);
            }
            
            // Get search data (with defaults applied)
            $searchData = $searchController->get($request, $defaultFilters);
            
            return [
                'search' => $searchData,
                'sortingOrder' => array_merge($defaultSorting, $searchData),
                'defaultFilters' => $defaultFilters,
                'defaultSorting' => $defaultSorting
            ];
        }
        
        // New signature: handleSearch($request, $query, $defaults)
        $query = $queryOrRoute;
        $defaults = $defaultFilters;
        
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
        
        // Support for old configuration format
        if (isset($config['date_filters'])) {
            $this->applyLegacyDateFilters($query, $searchData, $config);
        }
        
        if (isset($config['text_search'])) {
            $this->applyLegacyTextSearch($query, $searchData, $config);
        }
        
        // New format filters
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
     * Apply legacy date filters (backward compatibility)
     */
    protected function applyLegacyDateFilters($query, array $searchData, array $config)
    {
        $dateFilters = $config['date_filters'];
        $typeDate = $searchData['type_date'] ?? $dateFilters['default_type'] ?? 'start_date';
        
        // Apply year filter
        if (isset($searchData['year']) && !empty($searchData['year'])) {
            if (isset($dateFilters['relations'][$typeDate])) {
                $relation = $dateFilters['relations'][$typeDate];
                $query->whereHas($relation['name'], function($q) use ($typeDate, $searchData) {
                    $q->whereYear($typeDate, $searchData['year']);
                });
            } else {
                $query->whereYear($typeDate, $searchData['year']);
            }
        }
        
        // Apply month filter
        if (isset($searchData['month']) && !empty($searchData['month'])) {
            if (isset($dateFilters['relations'][$typeDate])) {
                $relation = $dateFilters['relations'][$typeDate];
                $query->whereHas($relation['name'], function($q) use ($typeDate, $searchData) {
                    $q->whereMonth($typeDate, $searchData['month']);
                });
            } else {
                $query->whereMonth($typeDate, $searchData['month']);
            }
        }
    }

    /**
     * Apply legacy text search (backward compatibility)
     */
    protected function applyLegacyTextSearch($query, array $searchData, array $config)
    {
        if (isset($searchData['text']) && !empty($searchData['text'])) {
            $textSearch = $config['text_search'];
            $columns = $textSearch['columns'] ?? ['title'];
            
            $query->where(function($q) use ($columns, $searchData) {
                foreach ($columns as $column) {
                    $q->orWhere($column, 'LIKE', '%' . $searchData['text'] . '%');
                }
            });
        }
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
        // Support for old configuration format
        if (isset($config['columns']) || isset($config['default'])) {
            return $this->applyLegacySorting($query, $searchData, $config);
        }
        
        // New format sorting
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

    /**
     * Apply legacy sorting (backward compatibility)
     */
    protected function applyLegacySorting($query, array $searchData, array $config)
    {
        $orderBy = $searchData['orderBy'] ?? 'default';
        $order = $searchData['order'] ?? 'desc';
        
        // Apply default sorting
        if ($orderBy === 'default' && isset($config['default']['callback'])) {
            $config['default']['callback']($query, $order);
            return $query;
        }
        
        // Apply column sorting
        if (isset($config['columns'][$orderBy]['callback'])) {
            $config['columns'][$orderBy]['callback']($query, $order);
        } else {
            $query->orderBy($orderBy, $order);
        }
        
        return $query;
    }
}
