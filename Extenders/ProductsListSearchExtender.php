<?php

namespace Okay\Modules\Sviat\ProductSearch\Extenders;

use Okay\Core\Modules\Extender\ExtensionInterface;
use Okay\Helpers\ProductsHelper;
use Okay\Modules\Sviat\ProductSearch\ExtendsEntities\LexicalProductFilter;
use Okay\Modules\Sviat\ProductSearch\Services\SearchTransliteration;

/** Якщо literal-пошук порожній — повторює getList у full-фазі. */
class ProductsListSearchExtender implements ExtensionInterface
{
    private ProductsHelper $productsHelper;

    private SearchTransliteration $transliteration;

    public function __construct(ProductsHelper $productsHelper, SearchTransliteration $transliteration)
    {
        $this->productsHelper = $productsHelper;
        $this->transliteration = $transliteration;
    }

    public function retryWithTransliterationFallback($products, $filter = [], $sortName = null, $excludedFields = null)
    {
        if (!empty($products)) {
            return $products;
        }

        $keyword = $filter['keyword'] ?? null;
        if ($keyword === null || $keyword === '') {
            return $products;
        }

        static $inFallback = false;
        if ($inFallback) {
            return $products;
        }

        if (!LexicalProductFilter::isLiteralKeywordPhase()) {
            return $products;
        }

        if (!$this->transliteration->shouldExpandVariants()) {
            return $products;
        }

        $inFallback = true;
        LexicalProductFilter::setKeywordPhase(LexicalProductFilter::PHASE_FULL);
        try {
            $products = $this->productsHelper->getList($filter, $sortName, $excludedFields);
        } finally {
            LexicalProductFilter::setKeywordPhase(LexicalProductFilter::PHASE_LITERAL);
            $inFallback = false;
        }

        return $products;
    }
}
