<?php

namespace Okay\Modules\Sviat\ProductSearch;

use Okay\Core\Design;
use Okay\Core\EntityFactory;
use Okay\Core\Languages;
use Okay\Core\Modules\Modules;
use Okay\Core\Image;
use Okay\Core\Money;
use Okay\Core\OkayContainer\Reference\ServiceReference as SR;
use Okay\Core\Request;
use Okay\Core\Router;
use Okay\Core\Settings;
use Okay\Helpers\MainHelper;
use Okay\Helpers\ProductsHelper;
use Okay\Modules\Sviat\ProductSearch\Extenders\CatalogIntentRanker;
use Okay\Modules\Sviat\ProductSearch\Extenders\FrontExtender;
use Okay\Modules\Sviat\ProductSearch\Extenders\ProductsListSearchExtender;
use Okay\Modules\Sviat\ProductSearch\Services\QueryNormalizer;
use Okay\Modules\Sviat\ProductSearch\Services\SearchTransliteration;
use Okay\Modules\Sviat\ProductSearch\Services\SuggestionProvider;

return [
    QueryNormalizer::class => [
        'class' => QueryNormalizer::class,
    ],

    SearchTransliteration::class => [
        'class' => SearchTransliteration::class,
        'arguments' => [
            new SR(Settings::class),
        ],
    ],

    SuggestionProvider::class => [
        'class' => SuggestionProvider::class,
        'arguments' => [
            new SR(QueryNormalizer::class),
            new SR(ProductsHelper::class),
            new SR(Router::class),
            new SR(Image::class),
            new SR(Money::class),
            new SR(MainHelper::class),
            new SR(Settings::class),
        ],
    ],

    CatalogIntentRanker::class => [
        'class' => CatalogIntentRanker::class,
        'arguments' => [
            new SR(Request::class),
            new SR(Languages::class),
            new SR(QueryNormalizer::class),
            new SR(SearchTransliteration::class),
        ],
    ],

    ProductsListSearchExtender::class => [
        'class' => ProductsListSearchExtender::class,
        'arguments' => [
            new SR(ProductsHelper::class),
            new SR(SearchTransliteration::class),
        ],
    ],

    FrontExtender::class => [
        'class' => FrontExtender::class,
        'arguments' => [
            new SR(Design::class),
            new SR(Settings::class),
            new SR(EntityFactory::class),
            new SR(Languages::class),
            new SR(Modules::class),
        ],
    ],
];
