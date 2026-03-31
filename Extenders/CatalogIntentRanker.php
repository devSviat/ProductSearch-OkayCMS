<?php

namespace Okay\Modules\Sviat\ProductSearch\Extenders;

use Okay\Core\Languages;
use Okay\Core\Modules\Extender\ExtensionInterface;
use Okay\Core\Request;
use Okay\Entities\ProductsEntity as BaseProductsEntity;
use Okay\Modules\Sviat\ProductSearch\ExtendsEntities\LexicalProductFilter;
use Okay\Modules\Sviat\ProductSearch\Services\QueryNormalizer;
use Okay\Modules\Sviat\ProductSearch\Services\SearchTransliteration;

/** Упорядкування в каталозі за keyword (плейсхолдери ps_* як у LexicalProductFilter). */
class CatalogIntentRanker implements ExtensionInterface
{
    private Request $request;

    private Languages $languages;

    private QueryNormalizer $normalizer;

    private SearchTransliteration $transliteration;

    public function __construct(
        Request $request,
        Languages $languages,
        QueryNormalizer $normalizer,
        SearchTransliteration $transliteration
    ) {
        $this->request = $request;
        $this->languages = $languages;
        $this->normalizer = $normalizer;
        $this->transliteration = $transliteration;
    }

    public function customOrder($result, $order = null, array $orderFields = [], array $additionalData = [])
    {
        if ($order !== 'position' || empty($additionalData['catalog_ps_intent'])) {
            return $result;
        }

        $langAlias = $this->languages->getLangAlias(BaseProductsEntity::getTableAlias());
        $tokens = $this->normalizer->toTokens((string) $additionalData['catalog_ps_intent']);
        if (empty($tokens)) {
            return $result;
        }

        $inStockFirst = null;
        if (($additionalData['in_stock_first'] ?? false) === true) {
            $inStockFirst = reset($result);
        }

        $strong = [];
        $weak = [];
        foreach ($tokens as $index => $token) {
            $variants = LexicalProductFilter::isLiteralKeywordPhase()
                ? [$token]
                : array_values($this->transliteration->tokenVariants($token));
            for ($v = 0, $n = count($variants); $v < $n; $v++) {
                $strong[] = "{$langAlias}.name LIKE :ps_r_{$index}_{$v}_pfx OR {$langAlias}.name LIKE :ps_r_{$index}_{$v}_spfx";
                $weak[] = "{$langAlias}.name LIKE :ps_w_{$index}_{$v}_name";
            }
        }

        $tier = sprintf(
            '(CASE WHEN (%s) THEN 3 WHEN (%s) THEN 2 ELSE 1 END) DESC',
            implode(' OR ', $strong),
            implode(' OR ', $weak)
        );

        array_unshift($result, $tier);
        if (!empty($inStockFirst)) {
            array_unshift($result, $inStockFirst);
        }

        return $result;
    }

    public function getOrderProductsAdditionalData($result)
    {
        $query = $this->normalizer->normalize((string) $this->request->get('query', null, null, false));
        if ($query === '') {
            $query = $this->normalizer->normalize((string) $this->request->get('keyword', null, null, false));
        }

        if ($query !== '') {
            $result['catalog_ps_intent'] = $query;
        }

        return $result;
    }
}
