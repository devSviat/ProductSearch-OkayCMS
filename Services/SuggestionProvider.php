<?php

namespace Okay\Modules\Sviat\ProductSearch\Services;

use Okay\Core\Image;
use Okay\Core\Money;
use Okay\Core\Router;
use Okay\Core\ServiceLocator;
use Okay\Core\Settings;
use Okay\Helpers\MainHelper;
use Okay\Helpers\ProductsHelper;

class SuggestionProvider
{
    private const REDIS_CACHE_SERVICE_CLASS = 'Okay\\Modules\\Sviat\\Redis\\Services\\RedisCacheService';

    private const REDIS_CACHE_KEY = 'product_search_suggestions';
    private const DEFAULT_SUGGESTION_LIMIT = 5;
    private const SUGGESTION_THUMB_SIZE = 50;
    private const DEFAULT_MIN_QUERY_LENGTH = 2;
    private const DEFAULT_NAME_PREVIEW_LENGTH = 80;
    private const DEFAULT_CACHE_TTL = 180;
    private const CACHE_KEY_VERSION = 3;

    private QueryNormalizer $normalizer;

    private ProductsHelper $productsHelper;

    private Router $router;

    private Image $image;

    private Money $money;

    private MainHelper $mainHelper;

    private Settings $settings;

    private bool $redisResolved = false;

    private ?object $redis = null;

    private ?array $configSnapshot = null;

    public function __construct(
        QueryNormalizer $normalizer,
        ProductsHelper $productsHelper,
        Router $router,
        Image $image,
        Money $money,
        MainHelper $mainHelper,
        Settings $settings
    ) {
        $this->normalizer = $normalizer;
        $this->productsHelper = $productsHelper;
        $this->router = $router;
        $this->image = $image;
        $this->money = $money;
        $this->mainHelper = $mainHelper;
        $this->settings = $settings;
    }

    public function buildResponse(string $rawQuery): \stdClass
    {
        $payload = new \stdClass();
        $payload->query = $this->normalizer->normalize($rawQuery);
        $payload->suggestions = $this->collectSuggestions($payload->query);

        return $payload;
    }

    /** @return list<\stdClass> */
    private function collectSuggestions(string $query): array
    {
        if ($query === '') {
            return [];
        }

        $cfg = $this->configSnapshot();

        if (!$cfg['feature_enabled']) {
            return [];
        }

        if (mb_strlen($query) < $cfg['min_query_length']) {
            return [];
        }

        $limit = $cfg['suggestion_limit'];

        $cached = $this->getCachedSuggestions($query, $limit, $cfg['currency_id']);
        if (is_array($cached)) {
            return $cached;
        }

        $currencySign = $this->mainHelper->getCurrentCurrency()->sign ?? '';

        $exclude = $this->productsHelper->getExcludeFields();
        if (!is_array($exclude)) {
            $exclude = ['description', 'meta_title', 'meta_keywords', 'meta_description'];
        }
        $exclude = array_values(array_unique(array_merge($exclude, ['annotation', 'special'])));

        $products = $this->productsHelper->getList([
            'keyword' => $query,
            'visible' => true,
            'limit' => $limit,
        ], null, $exclude);

        $list = [];
        foreach ($products as $product) {
            $thumb = null;
            if (isset($product->image)) {
                $thumb = $this->image->getResizeModifier(
                    $product->image->filename,
                    self::SUGGESTION_THUMB_SIZE,
                    self::SUGGESTION_THUMB_SIZE
                );
            }
            $suggestion = new \stdClass();
            $suggestion->price = $this->money->convert($product->variant->price);
            $suggestion->currency = $currencySign;
            $suggestion->value = $this->buildNamePreview((string) $product->name);
            $suggestion->url = SuggestionOutputSanitizer::href(
                $this->router->generateUrl('product', ['url' => $product->url])
            );
            $suggestion->image = SuggestionOutputSanitizer::imageSrc($thumb);
            $list[] = $suggestion;
        }

        $this->saveCachedSuggestions($query, $limit, $list, $cfg['currency_id']);

        return $list;
    }

    private function configSnapshot(): array
    {
        if ($this->configSnapshot !== null) {
            return $this->configSnapshot;
        }

        $currency = $this->mainHelper->getCurrentCurrency();
        $currencyId = is_object($currency) && isset($currency->id) ? (int) $currency->id : 0;

        $limit = $this->settings->get('sviat__product_search__suggestion_limit');
        $limit = $limit === null ? self::DEFAULT_SUGGESTION_LIMIT : (int) $limit;
        $limit = max(1, min(30, $limit));

        $minLen = $this->settings->get('sviat__product_search__min_query_length');
        $minLen = $minLen === null ? self::DEFAULT_MIN_QUERY_LENGTH : (int) $minLen;
        $minLen = max(1, min(10, $minLen));

        $previewLen = $this->settings->get('sviat__product_search__name_preview_length');
        $previewLen = $previewLen === null ? self::DEFAULT_NAME_PREVIEW_LENGTH : (int) $previewLen;
        $previewLen = max(40, min(255, $previewLen));

        $ttl = $this->settings->get('sviat__product_search__cache_ttl');
        $ttl = $ttl === null ? self::DEFAULT_CACHE_TTL : (int) $ttl;
        $ttl = max(0, min(86400, $ttl));

        $feature = $this->settings->get('sviat__product_search__enabled');
        $redisCache = $this->settings->get('sviat__product_search__redis_cache_enabled');

        $this->configSnapshot = [
            'feature_enabled' => $feature === null ? true : (bool) $feature,
            'redis_cache_enabled' => $redisCache === null ? true : (bool) $redisCache,
            'suggestion_limit' => $limit,
            'min_query_length' => $minLen,
            'name_preview_length' => $previewLen,
            'cache_ttl' => $ttl,
            'currency_id' => $currencyId,
        ];

        return $this->configSnapshot;
    }

    private function getCachedSuggestions(string $query, int $limit, int $currencyId): ?array
    {
        $redis = $this->redis();
        if ($redis === null) {
            return null;
        }

        $cached = $redis->get($this->buildCacheKey($redis, $query, $limit, $currencyId));
        return is_array($cached) ? $cached : null;
    }

    /** @param list<\stdClass> $suggestions */
    private function saveCachedSuggestions(string $query, int $limit, array $suggestions, int $currencyId): void
    {
        $redis = $this->redis();
        if ($redis === null) {
            return;
        }

        $ttl = $this->configSnapshot()['cache_ttl'];
        if ($ttl <= 0) {
            $ttl = $redis->getHelperTtl(self::REDIS_CACHE_KEY) ?? self::DEFAULT_CACHE_TTL;
        }
        $redis->set($this->buildCacheKey($redis, $query, $limit, $currencyId), $suggestions, $ttl);
    }

    /**
     * Формує versioned cache key для підказок.
     * Теги `plist:global` і `pall:global` скидають кеш при змінах товарів.
     */
    private function buildCacheKey(object $redis, string $query, int $limit, int $currencyId): string
    {
        return $redis->makeVersionedKey(
            self::REDIS_CACHE_KEY,
            ['plist:global', 'pall:global'],
            $this->cacheKeyMeta($query, $limit, $currencyId)
        );
    }

    private function cacheKeyMeta(string $query, int $limit, int $currencyId): array
    {
        return [
            'q' => mb_strtolower($query),
            'limit' => $limit,
            'img' => self::SUGGESTION_THUMB_SIZE,
            'cur' => $currencyId,
            'v' => self::CACHE_KEY_VERSION,
        ];
    }

    private function redis(): ?object
    {
        if ($this->redisResolved) {
            return $this->redis;
        }
        $this->redisResolved = true;
        $this->redis = null;

        $redisClass = self::REDIS_CACHE_SERVICE_CLASS;
        if (
            !$this->configSnapshot()['redis_cache_enabled']
            || !class_exists($redisClass)
        ) {
            return null;
        }

        try {
            $sl = ServiceLocator::getInstance();
            if (!$sl->hasService($redisClass)) {
                return null;
            }
            $redis = $sl->getService($redisClass);
            if (!is_object($redis) || !is_a($redis, $redisClass, true)) {
                return null;
            }
            if (!method_exists($redis, 'isEnabled') || !$redis->isEnabled()) {
                return null;
            }
            $this->redis = $redis;
        } catch (\Throwable $e) {
        }

        return $this->redis;
    }

    private function buildNamePreview(string $name): string
    {
        $maxLength = $this->configSnapshot()['name_preview_length'];
        if (mb_strlen($name) <= $maxLength) {
            return $name;
        }

        return rtrim(mb_substr($name, 0, $maxLength - 1)) . '...';
    }
}
