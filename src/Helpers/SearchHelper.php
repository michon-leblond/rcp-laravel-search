<?php

namespace Rcp\LaravelSearch\Helpers;

use Illuminate\Http\Request;
use Rcp\LaravelSearch\Controllers\SearchController;

class SearchHelper
{
    /**
     * Apply search filters to a query builder
     */
    public static function apply($query, Request $request, array $filters = [], array $defaults = [])
    {
        $searchController = new SearchController();
        $searchData = $searchController->get($request, $defaults);
        
        foreach ($filters as $field => $type) {
            if (isset($searchData[$field]) && !empty($searchData[$field])) {
                $value = $searchData[$field];
                
                switch ($type) {
                    case 'text':
                        $query->where($field, 'LIKE', "%{$value}%");
                        break;
                        
                    case 'date':
                        $query->whereDate($field, $value);
                        break;
                        
                    case 'status':
                        $query->where($field, $value);
                        break;
                        
                    case 'custom':
                        // For custom filters, call the method if it exists
                        $method = "apply" . ucfirst($field) . "Filter";
                        if (method_exists($query->getModel(), $method)) {
                            $query->getModel()->$method($query, $value);
                        }
                        break;
                        
                    default:
                        $query->where($field, $value);
                        break;
                }
            }
        }
        
        return $query;
    }

    /**
     * Get search data for a request
     */
    public static function getSearchData(Request $request, array $defaults = [])
    {
        $searchController = new SearchController();
        return $searchController->get($request, $defaults);
    }
}