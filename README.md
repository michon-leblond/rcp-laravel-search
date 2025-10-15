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

### 1. Utilisation avec le Trait Searchable (Recommandée)

Le trait `Searchable` offre une approche complète pour gérer la recherche, le filtrage et le tri dans vos contrôleurs :

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Controller;
use Rcp\LaravelSearch\Traits\Searchable;
use App\Models\PreOrder;
use Carbon\Carbon;

class PreOrderController extends Controller
{
    use Searchable;

    public function index(Request $request)
    {
        // Définir les filtres par défaut
        $defaultFilters = [
            "year" => Carbon::now()->year,
            "month" => Carbon::now()->month,
            "text" => null,
            "type_date" => 'start_date',
        ];

        // Définir l'ordre de tri par défaut
        $defaultSortingOrder = [
            'order' => 'desc',
            'orderBy' => 'default',
        ];

        // Gérer la recherche avec mise en cache automatique
        $searchData = $this->handleSearch($request, 'pre-orders', $defaultFilters, $defaultSortingOrder);
        
        // Obtenir la configuration de recherche
        $searchConfig = $this->getSearchConfiguration();
        
        // Construire la requête
        $preOrders = PreOrder::with('event', 'status', 'products');
        
        // Appliquer les filtres de recherche
        $preOrders = $this->applySearchFilters($preOrders, $searchData['search'], $searchConfig);
        
        // Appliquer le tri
        $preOrders = $this->applySorting($preOrders, $searchData['sortingOrder'], $searchConfig['sorting'] ?? []);
        
        return view('pre-orders.index', [
            'preOrders' => $preOrders->get(),
            'searchCache' => $searchData['search'],
            'sortingOrder' => $searchData['sortingOrder'],
        ]);
    }

    /**
     * Configuration de la recherche pour ce contrôleur
     */
    protected function getSearchConfiguration(): array
    {
        return [
            'date_filters' => [
                'default_type' => 'start_date',
                'allowed_types' => ['start_date', 'end_date', 'created_at'],
                'relations' => [
                    'start_date' => ['name' => 'event', 'table' => 'events'],
                    'end_date' => ['name' => 'event', 'table' => 'events'],
                ]
            ],
            'text_search' => [
                'columns' => ['title', 'number'],
                'relations' => [
                    'customer_name' => ['name' => 'customer', 'field' => 'name']
                ]
            ],
            'sorting' => [
                'default' => [
                    'callback' => function ($query, $order) {
                        return $query->join('events', 'events.id', '=', 'pre_orders.event_id')
                            ->orderBy('events.start_date', 'desc')
                            ->select('pre_orders.*');
                    }
                ],
                'columns' => [
                    'start_date' => [
                        'callback' => function ($query, $order) {
                            return $query->join('events', 'events.id', '=', 'pre_orders.event_id')
                                ->orderBy('events.start_date', $order)
                                ->select('pre_orders.*');
                        }
                    ],
                    'number' => [
                        'callback' => function ($query, $order) {
                            return $query->orderBy('number', $order);
                        }
                    ]
                ]
            ]
        ];
    }
}
```

### 2. Utilisation Simple avec SearchHelper

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

### 3. Configuration Avancée du Trait Searchable

#### Filtres de Date avec Relations

Le trait supporte les filtres de date complexes avec des relations :

```php
protected function getSearchConfiguration(): array
{
    return [
        'date_filters' => [
            'default_type' => 'start_date',  // Type de date par défaut
            'allowed_types' => ['start_date', 'end_date', 'created_at'],
            'relations' => [
                'start_date' => ['name' => 'event', 'table' => 'events'],
                'end_date' => ['name' => 'event', 'table' => 'events'],
                // created_at utilise la table principale (pas de relation)
            ]
        ],
    ];
}
```

#### Recherche Textuelle avec Relations

```php
protected function getSearchConfiguration(): array
{
    return [
        'text_search' => [
            'columns' => ['title', 'number', 'description'],
            'relations' => [
                'customer_name' => ['name' => 'customer', 'field' => 'name'],
                'event_title' => ['name' => 'event', 'field' => 'title']
            ]
        ],
    ];
}
```

#### Tri Personnalisé avec Callbacks

```php
protected function getSearchConfiguration(): array
{
    return [
        'sorting' => [
            'default' => [
                'callback' => function ($query, $order) {
                    return $query->join('events', 'events.id', '=', 'pre_orders.event_id')
                        ->orderBy('events.start_date', 'desc')
                        ->select('pre_orders.*');
                }
            ],
            'columns' => [
                'start_date' => [
                    'callback' => function ($query, $order) {
                        return $query->join('events', 'events.id', '=', 'pre_orders.event_id')
                            ->orderBy('events.start_date', $order)
                            ->select('pre_orders.*');
                    }
                ],
                'customer_name' => [
                    'callback' => function ($query, $order) {
                        return $query->join('customers', 'customers.id', '=', 'pre_orders.customer_id')
                            ->orderBy('customers.name', $order)
                            ->select('pre_orders.*');
                    }
                ],
                'number' => [
                    'callback' => function ($query, $order) {
                        return $query->orderBy('number', $order);
                    }
                ]
            ]
        ]
    ];
}
```

#### Utilisation des Méthodes Utilitaires

Le trait fournit des méthodes utilitaires pour des cas d'usage spécifiques :

```php
public function index(Request $request)
{
    $query = PreOrder::with('event', 'status');
    
    // Appliquer des filtres personnalisés
    $customFilters = [
        'status_id' => function ($query, $value) {
            return $query->where('status_id', $value);
        },
        'price_range' => function ($query, $value) {
            [$min, $max] = explode('-', $value);
            return $query->whereBetween('total_ht', [$min, $max]);
        }
    ];
    
    $searchData = $this->handleSearch($request, 'pre-orders', $defaultFilters, $defaultSorting);
    
    // Appliquer les filtres personnalisés
    $query = $this->applyMultipleFilters($query, $customFilters, $searchData['search']);
    
    $preOrders = $query->get();
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

### 4. Fonctionnalités Avancées

#### Cache Automatique des Paramètres de Recherche

Le trait gère automatiquement la mise en cache des paramètres de recherche par utilisateur et par route :

```php
// Les paramètres sont automatiquement sauvegardés et restaurés
$searchData = $this->handleSearch($request, 'products.index', $defaultFilters, $defaultSorting);

// Effacer le cache pour une route spécifique
Cache::forget($this->getSearchCacheKey('products.index'));
```

#### Filtres Personnalisés avec Callbacks

```php
protected function getSearchConfiguration(): array
{
    return [
        'custom_filters' => [
            'price_range' => function ($query, $value) {
                [$min, $max] = explode('-', $value);
                return $query->whereBetween('price', [$min, $max]);
            },
            'has_reviews' => function ($query, $value) {
                return $value ? $query->has('reviews') : $query->doesntHave('reviews');
            }
        ]
    ];
}
```

#### Recherche avec Relations Multiples

```php
protected function getSearchConfiguration(): array
{
    return [
        'text_search' => [
            'columns' => ['title', 'description'],
            'relations' => [
                'author_name' => ['name' => 'author', 'field' => 'name'],
                'category_name' => ['name' => 'category', 'field' => 'title'],
                'tag_names' => ['name' => 'tags', 'field' => 'name'] // relation many-to-many
            ]
        ]
    ];
}
```

### 5. API de Recherche

Le package inclut un contrôleur API pour gérer les recherches via AJAX :

```javascript
// Sauvegarder des paramètres de recherche
fetch('/api/search/store', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': token
    },
    body: JSON.stringify({
        year: 2024,
        month: 10,
        text: 'recherche'
    })
});

// Récupérer des paramètres de recherche
fetch('/api/search/get')
    .then(response => response.json())
    .then(data => console.log(data));

// Effacer le cache de recherche
fetch('/api/search/clear', { method: 'DELETE' });
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


# Package rcp-laravel-search

Guide pour basculer entre repository local (dev) et GitHub (prod).

## 🛠️ Mode DEV (Local)

**composer.json :**
```json
"rcp-laravel-search/rcp-laravel-search": "dev-main",
"repositories": [
    {
        "type": "path",
        "url": "../rcp-laravel-search",
        "options": { "symlink": true }
    }
]
```

**Commandes :**
```bash
composer update rcp-laravel-search/rcp-laravel-search
```

## 🚀 Mode PROD (GitHub)

**composer.json :**
```json
"rcp-laravel-search/rcp-laravel-search": "^1.2",
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/michon-leblond/rcp-laravel-search.git"
    }
]
```

**Commandes :**
```bash
composer clear-cache
rm -rf vendor/rcp-laravel-search
composer update rcp-laravel-search/rcp-laravel-search
```

## 🔄 Basculer

### DEV → PROD
1. Push + créer tag : `git tag v1.3.0 && git push origin v1.3.0`
2. Modifier composer.json (voir config PROD)
3. `composer clear-cache && composer update rcp-laravel-search/rcp-laravel-search`

### PROD → DEV
1. S'assurer que `../rcp-laravel-search` existe
2. Modifier composer.json (voir config DEV)  
3. `composer clear-cache && composer update rcp-laravel-search/rcp-laravel-search`

## 📝 Créer une version

```bash
cd ../rcp-laravel-search
git tag v1.3.0
git push origin v1.3.0
```

**Actuellement : Mode PROD avec `^1.2`** ✅

## Licence

MIT
