<?php

namespace Okay\Modules\Sviat\ProductSearch\Entities;

use Okay\Core\Entity\Entity;

class ProductSearchPopularQueryEntity extends Entity
{
    protected static $fields = [
        'id',
        'position',
    ];

    protected static $langFields = [
        'phrase',
    ];

    protected static $defaultOrderFields = [
        'position ASC',
    ];

    protected static $table = 'sviat__product_search_popular_queries';
    protected static $tableAlias = 'pspq';
    protected static $langTable = 'sviat__product_search_popular_queries';
    protected static $langObject = 'product_search_popular_query';
}
