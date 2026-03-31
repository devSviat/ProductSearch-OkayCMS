<?php

namespace Okay\Modules\Sviat\ProductSearch\Backend\Controllers;

use Okay\Admin\Controllers\IndexAdmin;
use Okay\Core\EntityFactory;
use Okay\Core\ServiceLocator;
use Okay\Modules\Sviat\ProductSearch\Entities\ProductSearchPopularQueryEntity;
use Okay\Modules\Sviat\ProductSearch\Services\SearchTransliteration;

class ProductSearchAdmin extends IndexAdmin
{
    public function fetch(EntityFactory $entityFactory)
    {
        $redisInfo = $this->getRedisInfo();

        $popularEntity = $entityFactory->get(ProductSearchPopularQueryEntity::class);

        if ($this->request->method('post')) {
            $this->settings->set('sviat__product_search__enabled', (int) $this->request->post('enabled'));
            $this->settings->set('sviat__product_search__min_query_length', (int) $this->request->post('min_query_length'));
            $this->settings->set('sviat__product_search__suggestion_limit', (int) $this->request->post('suggestion_limit'));
            $this->settings->set('sviat__product_search__name_preview_length', (int) $this->request->post('name_preview_length'));
            if ($redisInfo['available']) {
                $this->settings->set('sviat__product_search__redis_cache_enabled', (int) $this->request->post('redis_cache_enabled'));
                $this->settings->set('sviat__product_search__cache_ttl', (int) $this->request->post('cache_ttl'));
            }

            $this->settings->set(SearchTransliteration::SETTING_ENABLED, (int) $this->request->post('translit_enabled'));
            $this->settings->set(SearchTransliteration::SETTING_LAYOUT_LATIN_CYR, (int) $this->request->post('translit_layout_latin_cyr'));
            $this->settings->set(SearchTransliteration::SETTING_PHONETIC_LATIN_CYR, (int) $this->request->post('translit_phonetic_latin_cyr'));
            $this->settings->set(SearchTransliteration::SETTING_PHONETIC_CYR_LATIN, (int) $this->request->post('translit_phonetic_cyr_latin'));
            $this->settings->set(SearchTransliteration::SETTING_LAYOUT_CYR_LATIN, (int) $this->request->post('translit_layout_cyr_latin'));
            $this->settings->set(SearchTransliteration::SETTING_SHIFT_COMMAS, (int) $this->request->post('translit_shift_commas'));

            $this->savePopularQueriesFromPost($popularEntity);

            $this->design->assign('message_success', 'saved');
        }

        $this->migrateLegacyPopularSettingIfNeeded($popularEntity);

        $this->design->assign('product_search_enabled', $this->getSetting('sviat__product_search__enabled', 1));
        $this->design->assign('product_search_min_query_length', $this->getSetting('sviat__product_search__min_query_length', 2));
        $this->design->assign('product_search_suggestion_limit', $this->getSetting('sviat__product_search__suggestion_limit', 5));
        $this->design->assign('product_search_name_preview_length', $this->getSetting('sviat__product_search__name_preview_length', 80));
        $this->design->assign('product_search_redis_cache_enabled', $this->getSetting('sviat__product_search__redis_cache_enabled', 1));
        $this->design->assign('product_search_cache_ttl', $this->getSetting('sviat__product_search__cache_ttl', 180));
        $this->design->assign('product_search_translit_enabled', $this->getSetting(SearchTransliteration::SETTING_ENABLED, 1));
        $this->design->assign('product_search_translit_layout_latin_cyr', $this->getSetting(SearchTransliteration::SETTING_LAYOUT_LATIN_CYR, 1));
        $this->design->assign('product_search_translit_phonetic_latin_cyr', $this->getSetting(SearchTransliteration::SETTING_PHONETIC_LATIN_CYR, 1));
        $this->design->assign('product_search_translit_phonetic_cyr_latin', $this->getSetting(SearchTransliteration::SETTING_PHONETIC_CYR_LATIN, 1));
        $this->design->assign('product_search_translit_layout_cyr_latin', $this->getSetting(SearchTransliteration::SETTING_LAYOUT_CYR_LATIN, 1));
        $this->design->assign('product_search_translit_shift_commas', $this->getSetting(SearchTransliteration::SETTING_SHIFT_COMMAS, 1));
        $this->design->assign('product_search_redis_available', $redisInfo['available']);
        $this->design->assign('product_search_redis_enabled', $redisInfo['enabled']);
        $this->design->assign('product_search_popular_queries', $popularEntity->find());

        $this->response->setContent($this->design->fetch('product_search_admin.tpl'));
    }

    private function migrateLegacyPopularSettingIfNeeded(ProductSearchPopularQueryEntity $popularEntity): void
    {
        $raw = $this->settings->get('sviat__product_search__popular_queries');
        if ($raw === null || $raw === '' || $popularEntity->count() > 0) {
            return;
        }
        $lines = preg_split('/\r\n|\r|\n/', (string) $raw) ?: [];
        $pos = 0;
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            $pos++;
            $newId = $popularEntity->add((object) ['position' => $pos]);
            if ($newId) {
                $popularEntity->update($newId, ['phrase' => mb_substr($line, 0, 512)]);
            }
        }
        $this->settings->set('sviat__product_search__popular_queries', '');
    }

    private function savePopularQueriesFromPost(ProductSearchPopularQueryEntity $popularEntity): void
    {
        $deleteIds = $this->request->post('delete_ps_popular');
        if (!is_array($deleteIds)) {
            $deleteIds = $deleteIds !== null && $deleteIds !== '' ? [(int) $deleteIds] : [];
        }
        $deletedIds = [];
        $candidateIds = array_filter(array_map('intval', $deleteIds));
        if (!empty($candidateIds)) {
            $popularEntity->delete($candidateIds);
            foreach ($candidateIds as $id) {
                $deletedIds[$id] = true;
            }
        }

        $positions = $this->request->post('ps_popular_positions');
        if (is_array($positions)) {
            foreach ($positions as $id => $position) {
                $id = (int) $id;
                if ($id > 0 && empty($deletedIds[$id])) {
                    $popularEntity->update($id, ['position' => (int) $position]);
                }
            }
        }

        $rowIds = $this->request->post('ps_popular_id');
        $phrases = $this->request->post('ps_popular_phrase');
        if (!is_array($rowIds)) {
            $rowIds = $rowIds !== null && $rowIds !== '' ? [(int) $rowIds] : [];
        }
        if (!is_array($phrases)) {
            $phrases = $phrases !== null && $phrases !== '' ? [(string) $phrases] : [];
        }

        $maxPosition = is_array($positions) && !empty($positions) ? (int) max($positions) : 0;
        if ($maxPosition === 0) {
            foreach ($popularEntity->find() as $row) {
                if (empty($deletedIds[(int) $row->id])) {
                    $maxPosition = max($maxPosition, (int) $row->position);
                }
            }
        }

        if (!empty($rowIds)) {
            foreach ($rowIds as $i => $id) {
                $id = (int) $id;
                if ($id > 0 && !empty($deletedIds[$id])) {
                    continue;
                }
                $phrase = isset($phrases[$i]) ? trim((string) $phrases[$i]) : '';
                if ($id > 0) {
                    $popularEntity->update($id, ['phrase' => $phrase]);
                } elseif ($phrase !== '') {
                    $maxPosition++;
                    $newId = $popularEntity->add((object) [
                        'position' => $maxPosition,
                    ]);
                    if ($newId) {
                        $popularEntity->update($newId, ['phrase' => $phrase]);
                    }
                }
            }
        }
    }

    private function getSetting(string $key, int $default): int
    {
        $value = $this->settings->get($key);
        return $value === null ? $default : (int) $value;
    }

    private function getRedisInfo(): array
    {
        $serviceClass = 'Okay\\Modules\\Sviat\\Redis\\Services\\RedisCacheService';
        if (!class_exists($serviceClass)) {
            return ['available' => false, 'enabled' => false];
        }

        try {
            $sl = ServiceLocator::getInstance();
            if (!$sl->hasService($serviceClass)) {
                return ['available' => false, 'enabled' => false];
            }

            $redisService = $sl->getService($serviceClass);
            $enabled = method_exists($redisService, 'isEnabled') ? (bool) $redisService->isEnabled() : false;

            return ['available' => true, 'enabled' => $enabled];
        } catch (\Throwable $e) {
            return ['available' => false, 'enabled' => false];
        }
    }
}
