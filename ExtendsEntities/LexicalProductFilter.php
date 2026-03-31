<?php

namespace Okay\Modules\Sviat\ProductSearch\ExtendsEntities;

use Okay\Core\Languages;
use Okay\Core\Modules\AbstractModuleEntityFilter;
use Okay\Core\QueryFactory;
use Okay\Core\ServiceLocator;
use Okay\Entities\ProductsEntity as BaseProductsEntity;
use Okay\Modules\Sviat\ProductSearch\Services\QueryNormalizer;
use Okay\Modules\Sviat\ProductSearch\Services\SearchTransliteration;

/** Токени keyword + варіанти трансліту; двофазний пошук керується ProductsListSearchExtender. */
class LexicalProductFilter extends AbstractModuleEntityFilter
{
    public const PHASE_LITERAL = 'literal';
    public const PHASE_FULL = 'full';

    /** @var string */
    private static $keywordPhase = self::PHASE_LITERAL;

    /** @var array<string, object>|null */
    private static $filterServices;

    public static function setKeywordPhase(string $phase): void
    {
        self::$keywordPhase = $phase === self::PHASE_FULL ? self::PHASE_FULL : self::PHASE_LITERAL;
    }

    public static function isLiteralKeywordPhase(): bool
    {
        return self::$keywordPhase === self::PHASE_LITERAL;
    }

    public function apply($keyword)
    {
        if (self::$filterServices === null) {
            $sl = ServiceLocator::getInstance();
            self::$filterServices = [
                Languages::class => $sl->getService(Languages::class),
                QueryFactory::class => $sl->getService(QueryFactory::class),
                QueryNormalizer::class => $sl->getService(QueryNormalizer::class),
                SearchTransliteration::class => $sl->getService(SearchTransliteration::class),
            ];
        }

        $languages = self::$filterServices[Languages::class];
        $queryFactory = self::$filterServices[QueryFactory::class];
        $normalizer = self::$filterServices[QueryNormalizer::class];
        $transliteration = self::$filterServices[SearchTransliteration::class];

        $tokens = $normalizer->toTokens((string) $keyword);
        if (empty($tokens)) {
            return;
        }

        $tableAlias = BaseProductsEntity::getTableAlias();
        $langAlias = $languages->getLangAlias($tableAlias);
        $langId = (int) $languages->getLangId();

        foreach ($tokens as $index => $token) {
            $variants = self::isLiteralKeywordPhase()
                ? [$token]
                : array_values($transliteration->tokenVariants($token));
            if ($variants === []) {
                continue;
            }

            $perVariant = [];
            $featureLikes = [];
            foreach ($variants as $v => $variantForm) {
                $this->bindVariantValues($index, $v, $variantForm);
                $featureLikes[] = 'lfv.value LIKE :ps_w_' . $index . '_' . $v . '_feat';
                $perVariant[] = '(' . implode(' OR ', [
                    "{$langAlias}.name LIKE :ps_w_{$index}_{$v}_name",
                    "{$langAlias}.meta_keywords LIKE :ps_w_{$index}_{$v}_meta",
                    "{$langAlias}.annotation LIKE :ps_w_{$index}_{$v}_ann",
                    "{$langAlias}.description LIKE :ps_w_{$index}_{$v}_desc",
                    "{$tableAlias}.id IN (SELECT product_id FROM __variants WHERE sku LIKE :ps_w_{$index}_{$v}_sku)",
                ]) . ')';
            }

            $featureMatch = $queryFactory->newSelect();
            $featureMatch->from('__products_features_values AS pfv')
                ->cols(['product_id'])
                ->leftJoin('__features_values AS fv', 'pfv.value_id = fv.id')
                ->leftJoin(
                    '__lang_features_values AS lfv',
                    'fv.id = lfv.feature_value_id AND lfv.lang_id = ' . $langId
                )
                ->where('(' . implode(' OR ', $featureLikes) . ')');

            $this->select->where(
                '(' . implode(' OR ', $perVariant) . ' OR ' . $tableAlias . '.id IN (?))',
                $featureMatch
            );
        }
    }

    private function bindVariantValues(int $index, int $v, string $variantForm): void
    {
        $wildcard = '%' . $variantForm . '%';
        $this->select->bindValues([
            "ps_w_{$index}_{$v}_name" => $wildcard,
            "ps_w_{$index}_{$v}_meta" => $wildcard,
            "ps_w_{$index}_{$v}_ann" => $wildcard,
            "ps_w_{$index}_{$v}_desc" => $wildcard,
            "ps_w_{$index}_{$v}_sku" => $wildcard,
            "ps_w_{$index}_{$v}_feat" => $wildcard,
            "ps_r_{$index}_{$v}_pfx" => $variantForm . '%',
            "ps_r_{$index}_{$v}_spfx" => ' ' . $variantForm . '%',
        ]);
    }
}
