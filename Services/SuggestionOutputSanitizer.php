<?php

namespace Okay\Modules\Sviat\ProductSearch\Services;

/** Безпечні href/src/SVG для JSON підказок (без javascript:/data: тощо). */
final class SuggestionOutputSanitizer
{
    public static function svgForInlineHtml(string $svg): string
    {
        $out = preg_replace('#<\s*script\b[^>]*>.*?</\s*script\s*>#is', '', $svg);
        $out = preg_replace('#<\s*/\s*script\s*>#is', '', $out ?? '');
        $out = preg_replace('#\s+on\w+\s*=\s*(\'[^\']*\'|"[^"]*")#iu', '', $out ?? '');
        return trim($out ?? '');
    }

    public static function href(string $url): string
    {
        $url = str_replace(["\0", "\r", "\n"], '', trim($url));
        if ($url === '') {
            return '/';
        }
        if (preg_match('#^(?i)(javascript|data|vbscript)\s*:#', $url)) {
            return '#';
        }
        if (strpos($url, '//') === 0) {
            return '#';
        }
        if ($url[0] === '/' && preg_match('#^/[^\s]*$#u', $url)) {
            return $url;
        }
        $parts = parse_url($url);
        if (is_array($parts) && !empty($parts['scheme']) && in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            $validated = filter_var($url, FILTER_VALIDATE_URL);
            if ($validated !== false) {
                $path = $parts['path'] ?? '/';
                $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';
                return ($path !== '' ? $path : '/') . $query;
            }
        }

        return '#';
    }

    public static function imageSrc(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }
        $url = str_replace(["\0", "\r", "\n"], '', trim($url));
        if ($url === '') {
            return null;
        }
        if (preg_match('#^(?i)(javascript|data|vbscript)\s*:#', $url)) {
            return null;
        }
        if (strpos($url, '//') === 0) {
            return null;
        }
        if ($url[0] === '/' && preg_match('#^/[^\s]*$#u', $url)) {
            return $url;
        }
        if (preg_match('#^https?://#i', $url) && filter_var($url, FILTER_VALIDATE_URL) !== false) {
            return $url;
        }

        return null;
    }
}
