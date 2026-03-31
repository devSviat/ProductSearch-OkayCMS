<?php

namespace Okay\Modules\Sviat\ProductSearch\Init;

use Okay\Modules\Sviat\ProductSearch\Controllers\SuggestionController;

return [
    'ProductSearch.suggestions' => [
        'slug' => '/catalog/suggestions',
        'params' => [
            'controller' => SuggestionController::class,
            'method' => 'lookup',
        ],
        'to_front' => true,
    ],
];
