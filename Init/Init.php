<?php

namespace Okay\Modules\Sviat\ProductSearch\Init;

use Okay\Core\Modules\AbstractInit;
use Okay\Core\Modules\EntityField;
use Okay\Entities\ProductsEntity;
use Okay\Helpers\MainHelper;
use Okay\Helpers\ProductsHelper;
use Okay\Modules\Sviat\ProductSearch\Extenders\CatalogIntentRanker;
use Okay\Modules\Sviat\ProductSearch\Extenders\FrontExtender;
use Okay\Modules\Sviat\ProductSearch\Extenders\ProductsListSearchExtender;
use Okay\Modules\Sviat\ProductSearch\Entities\ProductSearchPopularQueryEntity;
use Okay\Modules\Sviat\ProductSearch\ExtendsEntities\LexicalProductFilter;

class Init extends AbstractInit
{
    public function install()
    {
        $this->setBackendMainController('ProductSearchAdmin');
        $this->registerPopularQueriesEntitySchema();
    }

    public function init()
    {

        $this->registerBackendController('ProductSearchAdmin');
        $this->addBackendControllerPermission('ProductSearchAdmin', 'products');

        $this->registerQueueExtension(
            [MainHelper::class, 'commonBeforeControllerProcedure'],
            [FrontExtender::class, 'assignProductSearchFrontendVars']
        );

        $this->registerChainExtension(
            [ProductsEntity::class, 'customOrder'],
            [CatalogIntentRanker::class, 'customOrder']
        );

        $this->registerChainExtension(
            [ProductsHelper::class, 'getOrderProductsAdditionalData'],
            [CatalogIntentRanker::class, 'getOrderProductsAdditionalData']
        );

        $this->registerChainExtension(
            [ProductsHelper::class, 'getList'],
            [ProductsListSearchExtender::class, 'retryWithTransliterationFallback']
        );

        $this->registerEntityFilter(
            ProductsEntity::class,
            'keyword',
            LexicalProductFilter::class,
            'apply'
        );
    }

    private function registerPopularQueriesEntitySchema(): void
    {
        $phraseField = (new EntityField('phrase'))->setTypeVarchar(512)->setIsLang();
        $this->migrateEntityTable(ProductSearchPopularQueryEntity::class, [
            (new EntityField('id'))->setIndexPrimaryKey()->setTypeInt(11, false)->setAutoIncrement(),
            $phraseField,
            (new EntityField('position'))->setTypeInt(11, false)->setDefault(0),
        ]);
        $this->migrateEntityField(ProductSearchPopularQueryEntity::class, $phraseField);
        $this->registerEntityLangInfo(
            ProductSearchPopularQueryEntity::class,
            'sviat__product_search_popular_queries',
            'product_search_popular_query'
        );
    }
}
