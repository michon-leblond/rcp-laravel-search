<?php

namespace Rcp\LaravelSearch\Traits;

use Illuminate\Http\Request;
use Rcp\LaravelSearch\Controllers\SearchController;

trait Searchable
{
    /**
     * Check if a value should be ignored for filtering
     * 
     * @param mixed $value
     * @return bool
     */
    protected function isEmptyFilterValue($value)
    {
        return $value === null || 
               $value === '' || 
               $value === 'all' || 
               (is_string($value) && trim($value) === '') ||
               (is_array($value) && empty($value));
    }

    /**
     * Get search data from request
     */
    protected function getSearchData(Request $request, array $defaultFilters = []): array
    {
        $searchController = new SearchController();
        
        // Store defaults if provided
        if (!empty($defaultFilters)) {
            $searchController->storeDefaults($request, $defaultFilters);
        }
        
        return $searchController->get($request, $defaultFilters);
    }

    /**
     * Get search and sorting data with defaults
     */
    protected function handleSearch(Request $request, array $defaultFilters = [], array $defaultSorting = []): array
    {
        $searchData = $this->getSearchData($request, $defaultFilters);
        
        // Merge sorting data properly
        $sortingOrder = array_merge($defaultSorting, [
            'orderBy' => $searchData['orderBy'] ?? $defaultSorting['orderBy'] ?? 'default',
            'order' => $searchData['order'] ?? $defaultSorting['order'] ?? 'desc'
        ]);
        
        return [
            'search' => $searchData,
            'sortingOrder' => $sortingOrder,
            'defaultFilters' => $defaultFilters,
            'defaultSorting' => $defaultSorting
        ];
    }

    /**
     * Apply search filters to the query
     */
    protected function applySearchFilters($query, array $searchData, array $config = [])
    {
        // Apply date filters if configured
        if (isset($config['date_filters'])) {
            $this->applyDateFilters($query, $searchData, $config['date_filters']);
        }
        
        // Apply text search if configured
        if (isset($config['text_search'])) {
            $this->applyTextSearch($query, $searchData, $config['text_search']);
        }
        
        // Apply other filters if configured
        if (isset($config['filters'])) {
            $this->applyCustomFilters($query, $searchData, $config['filters']);
        }
        
        return $query;
    }

    /**
     * Apply date filters (year, month, type_date)
     */
    protected function applyDateFilters($query, array $searchData, array $config)
    {
        $typeDate = $searchData['type_date'] ?? $config['default_type'] ?? 'start_date';
        
        // Apply year filter
        if (isset($searchData['year']) && !$this->isEmptyFilterValue($searchData['year'])) {
            if (isset($config['relations'][$typeDate])) {
                $relation = $config['relations'][$typeDate];
                $query->whereHas($relation['name'], function($q) use ($typeDate, $searchData) {
                    $q->whereYear($typeDate, $searchData['year']);
                });
            } else {
                $query->whereYear($query->getModel()->getTable() . '.' . $typeDate, $searchData['year']);
            }
        }
        
        // Apply month filter
        if (isset($searchData['month']) && !$this->isEmptyFilterValue($searchData['month'])) {
            if (isset($config['relations'][$typeDate])) {
                $relation = $config['relations'][$typeDate];
                $query->whereHas($relation['name'], function($q) use ($typeDate, $searchData) {
                    $q->whereMonth($typeDate, $searchData['month']);
                });
            } else {
                $query->whereMonth($query->getModel()->getTable() . '.' . $typeDate, $searchData['month']);
            }
        }
    }

    /**
     * Apply text search filters
     */
    protected function applyTextSearch($query, array $searchData, array $config)
    {
        if (isset($searchData['text'])) {
            $columns = $config['columns'] ?? ['title'];
            $relations = $config['relations'] ?? [];
            $tableName = $query->getModel()->getTable();
            
            $query->where(function($q) use ($columns, $relations, $searchData, $tableName) {
                foreach ($columns as $column) {
                    // Check if column has relation specification
                    if (isset($relations[$column])) {
                        $relation = $relations[$column];
                        $q->orWhereHas($relation['name'], function($subQ) use ($relation, $searchData) {
                            $subQ->where($relation['field'], 'LIKE', '%' . $searchData['text'] . '%');
                        });
                    } else {
                        $q->orWhere($tableName . '.' . $column, 'LIKE', '%' . $searchData['text'] . '%');
                    }
                }
            });
        }
    }

    /**
     * Apply custom filters
     */
    protected function applyCustomFilters($query, array $searchData, array $filters)
    {
        foreach ($filters as $field => $filterConfig) {
            if (!isset($searchData[$field]) || $this->isEmptyFilterValue($searchData[$field])) {
                continue;
            }
            
            $value = $searchData[$field];
            $type = is_array($filterConfig) ? ($filterConfig['type'] ?? 'text') : $filterConfig;
            
            switch ($type) {
                case 'relation':
                    $relation = $filterConfig['relation'] ?? $field;
                    $relationField = $filterConfig['field'] ?? 'id';
                    $query->whereHas($relation, function($q) use ($relationField, $value) {
                        $q->where($relationField, $value);
                    });
                    break;
                    
                case 'text':
                    $query->where($query->getModel()->getTable() . '.' . $field, 'LIKE', "%{$value}%");
                    break;
                    
                case 'exact':
                    $query->where($query->getModel()->getTable() . '.' . $field, $value);
                    break;
                    
                case 'custom':
                    $callback = $filterConfig['callback'] ?? null;
                    if ($callback && is_callable($callback)) {
                        $callback($query, $value, $searchData);
                    }
                    break;
                    
                default:
                    $query->where($query->getModel()->getTable() . '.' . $field, $value);
                    break;
            }
        }
    }

    /**
     * Apply sorting to the query
     */
    protected function applySorting($query, array $sortingOrder, array $sortingConfig = [])
    {
        $orderBy = $sortingOrder['orderBy'] ?? 'default';
        $order = $sortingOrder['order'] ?? 'desc';
        
        // Skip sorting if orderBy value should be ignored
        if ($this->isEmptyFilterValue($orderBy)) {
            return $query;
        }
        
        // Apply default sorting
        if ($orderBy === 'default' && isset($sortingConfig['default']['callback'])) {
            return $sortingConfig['default']['callback']($query, $order);
        }
        
        // Apply column sorting
        if (isset($sortingConfig['columns'][$orderBy]['callback'])) {
            return $sortingConfig['columns'][$orderBy]['callback']($query, $order);
        }
        
        // Fallback to simple column sorting
        return $query->orderBy($query->getModel()->getTable() . '.' . $orderBy, $order);
    }

}
