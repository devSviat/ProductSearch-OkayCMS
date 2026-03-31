<?php

namespace Okay\Modules\Sviat\ProductSearch\Extenders;

use Okay\Core\Design;
use Okay\Core\EntityFactory;
use Okay\Core\Languages;
use Okay\Core\Modules\Extender\ExtensionInterface;
use Okay\Core\Modules\Modules;
use Okay\Core\Settings;
use Okay\Modules\Sviat\ProductSearch\Entities\ProductSearchPopularQueryEntity;
use Okay\Modules\Sviat\ProductSearch\Services\SuggestionOutputSanitizer;

class FrontExtender implements ExtensionInterface
{
    private const FRONT_LANG_KEY_TITLE = 'sviat__product_search__popular_title';

    private Design $design;

    private Settings $settings;

    private EntityFactory $entityFactory;

    private Languages $languages;

    private Modules $modules;

    public function __construct(
        Design $design,
        Settings $settings,
        EntityFactory $entityFactory,
        Languages $languages,
        Modules $modules
    ) {
        $this->design = $design;
        $this->settings = $settings;
        $this->entityFactory = $entityFactory;
        $this->languages = $languages;
        $this->modules = $modules;
    }

    public function assignProductSearchFrontendVars(): void
    {
        $enabled = $this->settings->get('sviat__product_search__enabled');
        if ($enabled !== null && !(bool) $enabled) {
            return;
        }

        $this->design->assign('svgId', 'no_image');
        $svgInner = trim($this->design->fetch('svg.tpl'));
        $this->design->smarty->clearAssign('svgId');
        $this->design->assignJsVar(
            'product_search_no_image_svg',
            SuggestionOutputSanitizer::svgForInlineHtml($svgInner)
        );

        $this->design->assignJsVar('product_search_popular_queries', $this->popularPhrasesForStorefront());
        $this->design->assignJsVar('product_search_popular_title', $this->popularTitleForCurrentLang());
        $this->design->assignJsVar('product_search_min_chars', $this->resolveMinQueryLength());
    }

    private function popularTitleForCurrentLang(): string
    {
        $label = $this->languages->getLangLabel();
        if ($label === false || $label === '') {
            $label = 'ua';
        }
        $dict = $this->modules->getModuleFrontTranslations('Sviat', 'ProductSearch', $label);
        if (!empty($dict[self::FRONT_LANG_KEY_TITLE]) && is_string($dict[self::FRONT_LANG_KEY_TITLE])) {
            return $dict[self::FRONT_LANG_KEY_TITLE];
        }

        return 'Популярні запити';
    }

    /** @return string[] */
    private function popularPhrasesForStorefront(): array
    {
        try {
            $entity = $this->entityFactory->get(ProductSearchPopularQueryEntity::class);
        } catch (\Throwable $e) {
            return [];
        }

        $list = [];
        foreach ($entity->find() as $row) {
            $p = trim((string) ($row->phrase ?? ''));
            if ($p !== '') {
                $list[] = $p;
            }
        }

        return $list;
    }

    private function resolveMinQueryLength(): int
    {
        $length = $this->settings->get('sviat__product_search__min_query_length');
        $length = $length === null ? 2 : (int) $length;

        return max(1, min(10, $length));
    }
}
