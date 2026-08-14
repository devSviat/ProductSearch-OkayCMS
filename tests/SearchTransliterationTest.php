<?php

namespace Modules\Sviat\ProductSearch;

use Okay\Modules\Sviat\ProductSearch\Services\SearchTransliteration;
use PHPUnit\Framework\TestCase;

/**
 * Кожен варіант токена стає окремою умовою LIKE у запиті пошуку. Забагато
 * варіантів — важчий запит, замало — покупець із «ltkjyub» замість «делонги»
 * нічого не знаходить.
 */
class SearchTransliterationTest extends TestCase
{
    /** @var SearchTransliteration */
    private $transliteration;

    protected function setUp(): void
    {
        // Без Settings модуль працює на дефолтах: розширення варіантів увімкнене.
        $this->transliteration = new SearchTransliteration();
    }

    public function testOriginalTokenAlwaysComesFirst(): void
    {
        $variants = $this->transliteration->tokenVariants('delonghi');

        $this->assertSame('delonghi', $variants[0] ?? null);
    }

    public function testLatinTokenGetsCyrillicReadings(): void
    {
        $variants = $this->transliteration->tokenVariants('delonghi');

        $this->assertContains('делонґгі', $variants, 'транслітерація латиниці в кирилицю');
        $this->assertContains('вудщтпрш', $variants, 'набране не в тій розкладці');
    }

    public function testCyrillicTokenGetsLatinReadings(): void
    {
        $variants = $this->transliteration->tokenVariants('кофе');

        $this->assertContains('kofe', $variants);
        $this->assertContains('rjat', $variants, 'кирилиця, набрана в латинській розкладці');
    }

    /** Слово, набране не в тій розкладці, має знаходити оригінал. */
    public function testMislayoutIsReversible(): void
    {
        $variants = $this->transliteration->tokenVariants('ltkjyub');

        $this->assertContains('делонги', $variants);
    }

    public function testCaseAndSpacesDoNotMatter(): void
    {
        $this->assertSame(
            $this->transliteration->tokenVariants('delonghi'),
            $this->transliteration->tokenVariants('  DeLonghi  ')
        );
    }

    public function testEmptyTokenGivesNoVariants(): void
    {
        $this->assertSame([], $this->transliteration->tokenVariants(''));
        $this->assertSame([], $this->transliteration->tokenVariants('   '));
    }

    public function testVariantsAreUnique(): void
    {
        $variants = $this->transliteration->tokenVariants('ec685');

        $this->assertSame(array_values(array_unique($variants)), $variants);
    }

    /** Повторний виклик іде з кешу й має віддавати те саме. */
    public function testRepeatedCallReturnsTheSameSet(): void
    {
        $first = $this->transliteration->tokenVariants('делонги');
        $second = $this->transliteration->tokenVariants('делонги');

        $this->assertSame($first, $second);
    }
}
