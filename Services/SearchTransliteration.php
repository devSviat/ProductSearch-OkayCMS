<?php

namespace Okay\Modules\Sviat\ProductSearch\Services;

use Okay\Core\Settings;

/** Генерує варіанти токенів (латиниця/кирилиця та QWERTY-розкладки). */
class SearchTransliteration
{
    public const SETTING_ENABLED = 'sviat__product_search__translit_enabled';
    public const SETTING_LAYOUT_LATIN_CYR = 'sviat__product_search__translit_layout_latin_cyr';
    public const SETTING_PHONETIC_LATIN_CYR = 'sviat__product_search__translit_phonetic_latin_cyr';
    public const SETTING_PHONETIC_CYR_LATIN = 'sviat__product_search__translit_phonetic_cyr_latin';
    public const SETTING_LAYOUT_CYR_LATIN = 'sviat__product_search__translit_layout_cyr_latin';
    public const SETTING_SHIFT_COMMAS = 'sviat__product_search__translit_shift_commas';

    private const LAYOUT_UA = 'ua';

    private const LAYOUT_JCUKEN = 'jcuken';

    private const MAX_VARIANTS = 10;

    private const VARIANT_CACHE_MAX = 256;

    /** Порядок прапорців для підпису кешу в {@see translitCacheSignature()}. */
    private const SIGNATURE_KEYS = [
        self::SETTING_ENABLED,
        self::SETTING_LAYOUT_LATIN_CYR,
        self::SETTING_PHONETIC_LATIN_CYR,
        self::SETTING_PHONETIC_CYR_LATIN,
        self::SETTING_LAYOUT_CYR_LATIN,
        self::SETTING_SHIFT_COMMAS,
    ];

    /** @var list<string> */
    private const EXPANSION_SETTING_KEYS = [
        self::SETTING_LAYOUT_LATIN_CYR,
        self::SETTING_PHONETIC_LATIN_CYR,
        self::SETTING_PHONETIC_CYR_LATIN,
        self::SETTING_LAYOUT_CYR_LATIN,
        self::SETTING_SHIFT_COMMAS,
    ];

    /** @var array<string, string[]> */
    private static $tokenVariantCache = [];

    /** @var list<string>|null */
    private static $layoutIdsMemo;

    /** @var Settings|null */
    private $settings;

    /** @var string|null */
    private $translitSignatureMemo;

    public function __construct(?Settings $settings = null)
    {
        $this->settings = $settings;
    }

    public function shouldExpandVariants(): bool
    {
        if (!$this->isTransliterationEnabled()) {
            return false;
        }
        if ($this->settings === null) {
            return true;
        }

        foreach (self::EXPANSION_SETTING_KEYS as $key) {
            if ($this->readBool($key, true)) {
                return true;
            }
        }

        return false;
    }

    private function isTransliterationEnabled(): bool
    {
        return $this->readBool(self::SETTING_ENABLED, true);
    }

    /** @return string[] */
    public function tokenVariants(string $token): array
    {
        $low = mb_strtolower(trim($token));
        if ($low === '') {
            return [];
        }

        $cacheKey = $low . "\0" . $this->translitCacheSignature();
        if (isset(self::$tokenVariantCache[$cacheKey])) {
            return self::$tokenVariantCache[$cacheKey];
        }

        if (!$this->isTransliterationEnabled()) {
            return $this->rememberCache($cacheKey, [$low]);
        }

        [$hasCyrillic, $hasLatin] = self::detectScripts($low);

        $variants = [$low];

        if ($hasLatin && !$hasCyrillic) {
            $layoutFromKeyboard = [];
            if ($this->readBool(self::SETTING_LAYOUT_LATIN_CYR, true)) {
                foreach (self::layoutIds() as $layoutId) {
                    $conv = self::latinKeyboardMislayoutToCyrillic($low, $layoutId);
                    if ($conv !== '' && $conv !== $low) {
                        $variants[] = $conv;
                        $layoutFromKeyboard[$conv] = true;
                    }
                }
            }

            if ($this->readBool(self::SETTING_PHONETIC_LATIN_CYR, true)) {
                $cyr = self::latinToCyrillicUk($low);
                if ($cyr !== '' && $cyr !== $low && !isset($layoutFromKeyboard[$cyr])) {
                    $variants[] = $cyr;
                }
            }
        }

        if ($hasCyrillic) {
            if ($this->readBool(self::SETTING_PHONETIC_CYR_LATIN, true)) {
                $lat = self::cyrillicToLatinUk($low);
                if ($lat !== '' && $lat !== $low) {
                    $variants[] = $lat;
                }
            }

            if ($this->readBool(self::SETTING_LAYOUT_CYR_LATIN, true)) {
                foreach (self::layoutIds() as $layoutId) {
                    $latKeys = self::cyrillicKeyboardToLatinUsingMap($low, self::cyrillicToLatinKeyMapForLayout($layoutId));
                    if ($latKeys !== '' && $latKeys !== $low) {
                        $variants[] = $latKeys;
                    }
                    if ($this->readBool(self::SETTING_SHIFT_COMMAS, true) && $latKeys !== '') {
                        foreach (self::latinKeyboardShiftAlternates($latKeys) as $alt) {
                            if ($alt !== '' && $alt !== $latKeys) {
                                $variants[] = $alt;
                            }
                        }
                    }
                }
            }
        }

        $out = [];
        foreach ($variants as $v) {
            if ($v !== '' && !isset($out[$v])) {
                $out[$v] = true;
            }
        }
        $result = array_slice(array_keys($out), 0, self::MAX_VARIANTS);

        return $this->rememberCache($cacheKey, $result);
    }

    private function rememberCache(string $cacheKey, array $result): array
    {
        if (count(self::$tokenVariantCache) >= self::VARIANT_CACHE_MAX) {
            self::$tokenVariantCache = [];
        }
        self::$tokenVariantCache[$cacheKey] = $result;

        return $result;
    }

    private function translitCacheSignature(): string
    {
        if ($this->translitSignatureMemo !== null) {
            return $this->translitSignatureMemo;
        }
        if ($this->settings === null) {
            return $this->translitSignatureMemo = 'default';
        }

        $bits = '';
        foreach (self::SIGNATURE_KEYS as $key) {
            $bits .= (string) (int) $this->readBool($key, true);
        }

        return $this->translitSignatureMemo = $bits;
    }

    private function readBool(string $key, bool $default): bool
    {
        if ($this->settings === null) {
            return $default;
        }
        $v = $this->settings->get($key);

        return $v === null ? $default : (bool) (int) $v;
    }

    /** @return array{0: bool, 1: bool} */
    private static function detectScripts(string $s): array
    {
        static $cyrRx = '/[\x{0400}-\x{052F}]/u';
        static $latRx = '/[A-Za-z]/';

        return [
            (bool) preg_match($cyrRx, $s),
            (bool) preg_match($latRx, $s),
        ];
    }

    /** @return list<string> */
    private static function layoutIds(): array
    {
        if (self::$layoutIdsMemo !== null) {
            return self::$layoutIdsMemo;
        }

        /** @var list<string> $ids */
        $ids = array_keys(self::$latinKeyToCyrillic);

        return self::$layoutIdsMemo = $ids;
    }

    private static function latinKeyboardMislayoutToCyrillic(string $s, string $layout): string
    {
        $map = self::$latinKeyToCyrillic[$layout] ?? [];
        $out = '';
        $len = mb_strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $ch = mb_substr($s, $i, 1);
            if (isset($map[$ch])) {
                $out .= $map[$ch];
                continue;
            }
            $lower = mb_strtolower($ch, 'UTF-8');
            if (isset($map[$lower])) {
                $out .= $map[$lower];
                continue;
            }
            $out .= $ch;
        }

        return $out;
    }

    private static function cyrillicKeyboardToLatinUsingMap(string $s, array $keyMap): string
    {
        $out = '';
        $len = mb_strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $ch = mb_substr($s, $i, 1);
            $lk = mb_strtolower($ch, 'UTF-8');
            $out .= $keyMap[$lk] ?? $ch;
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    private static function cyrillicToLatinKeyMapForLayout(string $layout): array
    {
        if (isset(self::$cyrillicToLatinKeyByLayout[$layout])) {
            return self::$cyrillicToLatinKeyByLayout[$layout];
        }

        $map = [];
        foreach (self::$latinKeyToCyrillic[$layout] as $lat => $cyr) {
            if ($cyr !== '' && !isset($map[$cyr])) {
                $map[$cyr] = $lat;
            }
        }

        self::$cyrillicToLatinKeyByLayout[$layout] = $map;

        return self::$cyrillicToLatinKeyByLayout[$layout];
    }

    private static function latinKeyboardShiftAlternates(string $primary): array
    {
        if ($primary === '') {
            return [];
        }

        $out = [];
        if (($p = mb_strpos($primary, ',')) !== false) {
            $out[] = mb_substr($primary, 0, $p) . '<' . mb_substr($primary, $p + 1);
        }
        if (($p = mb_strpos($primary, '.')) !== false) {
            $out[] = mb_substr($primary, 0, $p) . '>' . mb_substr($primary, $p + 1);
        }

        return array_values(array_unique($out));
    }

    private static function latinToCyrillicUk(string $s): string
    {
        $multi = self::latinMultiMap();
        $out = '';
        $len = mb_strlen($s);
        for ($i = 0; $i < $len;) {
            $matched = false;
            foreach ($multi as $lat => $cyr) {
                $ll = strlen($lat);
                if ($ll === 0) {
                    continue;
                }
                if (mb_substr($s, $i, $ll) === $lat) {
                    $out .= $cyr;
                    $i += $ll;
                    $matched = true;
                    break;
                }
            }
            if ($matched) {
                continue;
            }
            $ch = mb_substr($s, $i, 1);
            $i++;
            $lower = $ch >= 'A' && $ch <= 'Z' ? chr(ord($ch) + 32) : ($ch >= 'a' && $ch <= 'z' ? $ch : mb_strtolower($ch));
            if (isset(self::$latinSingle[$lower])) {
                $out .= self::$latinSingle[$lower];
            } else {
                $out .= $ch;
            }
        }

        return $out;
    }

    private static function cyrillicToLatinUk(string $s): string
    {
        $map = self::$cyrillicSingle;
        $out = '';
        $len = mb_strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $ch = mb_substr($s, $i, 1);
            $lk = mb_strtolower($ch, 'UTF-8');
            $out .= $map[$lk] ?? $ch;
        }

        return $out;
    }

    private static function latinMultiMap(): array
    {
        if (self::$latinMulti !== null) {
            return self::$latinMulti;
        }

        $raw = [
            'shch' => 'щ',
            'sch' => 'щ',
            'zh' => 'ж',
            'kh' => 'х',
            'ts' => 'ц',
            'ch' => 'ч',
            'sh' => 'ш',
            'ye' => 'є',
            'yi' => 'ї',
            'yu' => 'ю',
            'ya' => 'я',
            'yo' => 'йо',
            'ia' => 'я',
            'iu' => 'ю',
        ];
        uksort($raw, static function ($a, $b) {
            return strlen($b) <=> strlen($a);
        });
        self::$latinMulti = $raw;

        return self::$latinMulti;
    }

    /**
     * Латинська QWERTY → кирилиця: `ua` (українська), `jcuken` (ЙЦУКЕН).
     *
     * @var array<string, array<string, string>>
     */
    private static $latinKeyToCyrillic = [
        self::LAYOUT_UA => [
            'q' => 'й', 'w' => 'ц', 'e' => 'у', 'r' => 'к', 't' => 'е', 'y' => 'н',
            'u' => 'г', 'i' => 'ш', 'o' => 'щ', 'p' => 'з', '[' => 'х', ']' => 'ї',
            'a' => 'ф', 's' => 'і', 'd' => 'в', 'f' => 'а', 'g' => 'п', 'h' => 'р',
            'j' => 'о', 'k' => 'л', 'l' => 'д', ';' => 'ж', '\'' => 'є',
            'z' => 'я', 'x' => 'ч', 'c' => 'с', 'v' => 'м', 'b' => 'и', 'n' => 'т',
            'm' => 'ь', ',' => 'б', '.' => 'ю',
            '<' => 'б', '>' => 'ю',
        ],
        self::LAYOUT_JCUKEN => [
            '`' => 'ё',
            'q' => 'й', 'w' => 'ц', 'e' => 'у', 'r' => 'к', 't' => 'е', 'y' => 'н',
            'u' => 'г', 'i' => 'ш', 'o' => 'щ', 'p' => 'з', '[' => 'х', ']' => 'ъ',
            'a' => 'ф', 's' => 'ы', 'd' => 'в', 'f' => 'а', 'g' => 'п', 'h' => 'р',
            'j' => 'о', 'k' => 'л', 'l' => 'д', ';' => 'ж', '\'' => 'э',
            'z' => 'я', 'x' => 'ч', 'c' => 'с', 'v' => 'м', 'b' => 'и', 'n' => 'т',
            'm' => 'ь', ',' => 'б', '.' => 'ю',
            '<' => 'б', '>' => 'ю',
        ],
    ];

    /** @var array<string, array<string, string>> */
    private static $cyrillicToLatinKeyByLayout = [];

    /** @var array<string, string>|null */
    private static $latinMulti;

    /** @var array<string, string> */
    private static $latinSingle = [
        'a' => 'а', 'b' => 'б', 'c' => 'к', 'd' => 'д', 'e' => 'е', 'f' => 'ф',
        'g' => 'ґ', 'h' => 'г', 'i' => 'і', 'j' => 'й', 'k' => 'к', 'l' => 'л',
        'm' => 'м', 'n' => 'н', 'o' => 'о', 'p' => 'п', 'q' => 'к', 'r' => 'р',
        's' => 'с', 't' => 'т', 'u' => 'у', 'v' => 'в', 'w' => 'в', 'x' => 'кс',
        'y' => 'и', 'z' => 'з',
    ];

    /** @var array<string, string> */
    private static $cyrillicSingle = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'h', 'ґ' => 'g', 'д' => 'd',
        'е' => 'e', 'є' => 'ye', 'ж' => 'zh', 'з' => 'z', 'и' => 'y', 'і' => 'i',
        'ї' => 'yi', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
        'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
        'ф' => 'f', 'х' => 'kh', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch',
        'ь' => '', 'ю' => 'yu', 'я' => 'ya',
    ];
}
