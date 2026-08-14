<?php

namespace Modules\Sviat\ProductSearch;

use Okay\Modules\Sviat\ProductSearch\Services\QueryNormalizer;
use PHPUnit\Framework\TestCase;

/**
 * Те, що приходить у keyword, читає безпосередньо покупець, а далі воно стає
 * умовами SQL. Нормалізатор — єдине місце, де довжина й кількість токенів
 * обмежені, тож межі перевіряємо явно.
 */
class QueryNormalizerTest extends TestCase
{
    /** @var QueryNormalizer */
    private $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new QueryNormalizer();
    }

    public function testCollapsesWhitespaceAndTrims(): void
    {
        $this->assertSame('delonghi ремонт', $this->normalizer->normalize("  delonghi \n\t ремонт  "));
    }

    public function testStripsTags(): void
    {
        $this->assertSame('alert(1)', $this->normalizer->normalize('<script>alert(1)</script>'));
    }

    public function testCutsOverlongQueries(): void
    {
        $normalized = $this->normalizer->normalize(str_repeat('a', 200));

        $this->assertSame(120, mb_strlen($normalized));
    }

    public function testTokensDropSingleCharacterNoise(): void
    {
        $this->assertSame(['delonghi', 'ec685'], $this->normalizer->toTokens('delonghi a ec685'));
    }

    public function testTokensAreUnique(): void
    {
        $this->assertSame(['delonghi'], $this->normalizer->toTokens('delonghi delonghi'));
    }

    /** Кожен токен — це окрема умова в запиті, тож їх кількість обмежена. */
    public function testAtMostFiveTokens(): void
    {
        $tokens = $this->normalizer->toTokens('aa bb cc dd ee ff gg');

        $this->assertSame(['aa', 'bb', 'cc', 'dd', 'ee'], $tokens);
    }

    public function testEmptyQueryGivesNoTokens(): void
    {
        $this->assertSame([], $this->normalizer->toTokens('   '));
        $this->assertSame([], $this->normalizer->toTokens(''));
    }

    public function testCyrillicIsCountedByCharactersNotBytes(): void
    {
        $tokens = $this->normalizer->toTokens('ем');

        $this->assertSame(['ем'], $tokens, 'двосимвольний кириличний токен має лишатись');
    }
}
