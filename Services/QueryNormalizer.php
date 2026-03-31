<?php

namespace Okay\Modules\Sviat\ProductSearch\Services;

class QueryNormalizer
{
    private const MAX_QUERY_LENGTH = 120;
    private const MAX_TOKENS = 5;
    private const MIN_TOKEN_LENGTH = 2;

    public function normalize(string $rawQuery): string
    {
        $plain = strip_tags($rawQuery);
        $plain = preg_replace('/\s+/u', ' ', $plain);
        $plain = mb_substr((string) $plain, 0, self::MAX_QUERY_LENGTH);

        return trim((string) $plain);
    }

    /** @return string[] */
    public function toTokens(string $rawQuery): array
    {
        $query = $this->normalize($rawQuery);
        if ($query === '') {
            return [];
        }

        $parts = preg_split('/\s+/u', $query) ?: [];
        $tokens = array_filter($parts, static function ($part) {
            return $part !== '' && mb_strlen($part) >= self::MIN_TOKEN_LENGTH;
        });

        $tokens = array_values(array_unique($tokens));

        return array_slice($tokens, 0, self::MAX_TOKENS);
    }
}
