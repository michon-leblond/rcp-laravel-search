# RCP Laravel Search

Un package Laravel réutilisable pour gérer facilement la recherche et le filtrage dans vos applications.

## Installation

```bash
composer require rcp-laravel-search/rcp-laravel-search
```

Le package sera automatiquement découvert par Laravel. Publiez la configuration :

```bash
php artisan vendor:publish --tag=rcp-search-config
```

## Utilisation

### 1. Utilisation Simple avec SearchHelper

Pour des cas simples de recherche :

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Rcp\LaravelSearch\Helpers\SearchHelper;
use App\Models\Product;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::query();
        
        // Définir les filtres et les valeurs par défaut
        $filters = [
            'name' => 'text',
            'status' => 'exact',
            'created_at' => 'date'
        ];
        
        $defaults = [
            'status' => 'active'
        ];
        
        // Appliquer la recherche
        $products = SearchHelper::apply($query, $request, $filters, $defaults)
            ->paginate(15);
        
        // Récupérer les données de recherche pour la vue
        $searchData = SearchHelper::getSearchData($request, $defaults);
        
        return inertia('Products/Index', [
            'products' => $products,
            'searchData' => $searchData
        ]);
    }
}
```

### 2. Utilisation Avancée avec le Trait Searchable

Pour des cas plus complexes avec relations et tri personnalisé :

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Rcp\LaravelSearch\Traits\Searchable;
use App\Models\PreOrder;

class PreOrderController extends Controller
{
    use Searchable;

    public function index(Request $request)
    {
        $query = PreOrder::with('event');
        
        // Définir les valeurs par défaut
        $defaults = [
            'year' => date('Y'),
            'month' => date('n')
        ];
        
        // Appliquer la recherche avec la configuration
        $preOrders = $this->handleSearch($request, $query, $defaults)
            ->paginate(15);
        
        return inertia('PreOrders/Index', [
            'preOrders' => $preOrders
        ]);
    }

    /**
     * Configuration de la recherche
     */
    protected function getSearchConfiguration(): array
    {
        return [
            'filters' => [
                'year' => [
                    'type' => 'date',
                    'year_field' => 'events.start_date'
                ],
                'month' => [
                    'type' => 'date', 
                    'month_field' => 'events.start_date'
                ],
                'event_id' => [
                    'type' => 'relation',
                    'relation' => 'event',
                    'field' => 'id'
                ],
                'status' => [
                    'type' => 'exact'
                ],
                'customer_name' => [
                    'type' => 'custom',
                    'callback' => function($query, $value) {
                        $query->whereHas('customer', function($q) use ($value) {
                            $q->where('name', 'LIKE', "%{$value}%");
                        });
                    }
                ]
            ],
            'sorts' => [
                'created_at' => 'created_at',
                'event_date' => [
                    'callback' => function($query, $direction) {
                        $query->join('events', 'pre_orders.event_id', '=', 'events.id')
                              ->orderBy('events.start_date', $direction);
                    }
                ]
            ]
        ];
    }
}
```

## Configuration

Le fichier de configuration `config/rcp-search.php` permet de personnaliser :

```php
return [
    'cache' => [
        'ttl' => 60, // Durée de vie du cache en minutes
        'prefix' => 'search_', // Préfixe des clés de cache
    ],
    
    'defaults' => [
        'filters' => [],
        'sorts' => [],
        'pagination' => 15,
    ],
];
```

## Types de Filtres

### Filtres de Base

- `text` : Recherche avec LIKE
- `exact` : Correspondance exacte
- `date` : Filtre par date

### Filtres Avancés

- `relation` : Filtrage via les relations Eloquent
- `custom` : Callback personnalisé pour logique complexe

### Configuration des Dates

```php
'start_date' => [
    'type' => 'date',
    'year_field' => 'events.start_date',   // Filtre par année
    'month_field' => 'events.start_date',  // Filtre par mois
    'date_field' => 'events.start_date'    // Filtre par date exacte
]
```

## Tri

### Tri Simple

```php
'sorts' => [
    'name' => 'name',
    'created_at' => 'created_at'
]
```

### Tri Personnalisé

```php
'sorts' => [
    'event_date' => [
        'callback' => function($query, $direction) {
            $query->join('events', 'pre_orders.event_id', '=', 'events.id')
                  ->orderBy('events.start_date', $direction);
        }
    ]
]
```

## Cache

Le package utilise le système de cache de Laravel pour persister les paramètres de recherche. Chaque utilisateur et route a sa propre clé de cache.

## Licence

MIT
