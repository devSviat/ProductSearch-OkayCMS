<?php

namespace Modules\Sviat\ProductSearch;

use Aura\SqlQuery\QueryFactory as AuraQueryFactory;
use Okay\Core\Languages;
use Okay\Core\QueryFactory;
use Okay\Core\QueryFactory\Select;
use Okay\Modules\Sviat\ProductSearch\ExtendsEntities\LexicalProductFilter;
use Okay\Modules\Sviat\ProductSearch\Services\QueryNormalizer;
use Okay\Modules\Sviat\ProductSearch\Services\SearchTransliteration;
use PHPUnit\Framework\TestCase;

/**
 * Фільтр збирає SQL, який виконується лише в рантаймі, тож поламаний запит
 * не валить ні завантаження класу, ні звичайні тести: сторінка пошуку віддає
 * HTTP 200 з порожньою видачею. Саме так міграція на aura/sqlquery 3 і
 * зламала пошук — тому тут перевіряється текст запиту.
 */
class LexicalProductFilterTest extends TestCase
{
    protected function tearDown(): void
    {
        // Сервіси фільтра кешуються в статиці — прибираємо, щоб не текло
        // між тестами.
        $reflected = new \ReflectionProperty(LexicalProductFilter::class, 'filterServices');
        if (PHP_VERSION_ID < 80100) { $reflected->setAccessible(true); }
        $reflected->setValue(null, null);

        parent::tearDown();
    }

    public function testNoPositionalPlaceholderReachesTheStatement(): void
    {
        $select = $this->applyKeyword('delonghi');

        $this->assertStringNotContainsString(
            '?',
            $select->getStatement(),
            'aura/sqlquery 3 не приймає позиційних привʼязок'
        );
    }

    /**
     * Цитувальник aura проходиться по всій умові where() і на «AS alias ON»
     * ставить лапки не там, де треба: `__lang_features_values` AS `lfv ON ...`.
     * Запит стає синтаксично невалідним, а помилка видно лише в лозі.
     */
    public function testFeatureSubqueryHasNeitherAliasesNorJoins(): void
    {
        $statement = $this->applyKeyword('delonghi')->getStatement();

        $subquery = $this->featureSubquery($statement);

        $this->assertStringNotContainsString(' AS ', $subquery, 'підзапит не має вживати аліасів');
        $this->assertStringNotContainsString('JOIN', $subquery, 'підзапит не має вживати JOIN');
        $this->assertStringContainsString('__products_features_values', $subquery);
        $this->assertStringContainsString('__lang_features_values', $subquery);
    }

    public function testFeatureSubqueryFiltersByTheCurrentLanguage(): void
    {
        $subquery = $this->featureSubquery($this->applyKeyword('delonghi')->getStatement());

        $this->assertStringContainsString('lang_id = 3', $subquery);
    }

    /** Кожен токен додає власний набір привʼязок, і всі вони мають значення. */
    public function testEveryTokenBindsItsOwnWildcards(): void
    {
        $select = $this->applyKeyword('delonghi ремонт');
        $binds = $select->getBindValues();

        foreach (['name', 'meta', 'ann', 'desc', 'sku', 'feat'] as $suffix) {
            $this->assertSame('%delonghi%', $binds["ps_w_0_0_{$suffix}"] ?? null, $suffix);
            $this->assertSame('%ремонт%', $binds["ps_w_1_0_{$suffix}"] ?? null, $suffix);
        }
    }

    /** Кожен плейсхолдер із запиту має привʼязане значення, і навпаки. */
    public function testEveryPlaceholderIsBound(): void
    {
        $select = $this->applyKeyword('delonghi ремонт');

        preg_match_all('~:([a-z0-9_]+)~i', $select->getStatement(), $matches);
        $placeholders = array_unique($matches[1]);
        $bound = array_keys($select->getBindValues());

        $this->assertSame([], array_values(array_diff($placeholders, $bound)), 'плейсхолдер без значення');
    }

    public function testEmptyKeywordTouchesNothing(): void
    {
        $before = $this->select();
        $statement = $before->getStatement();

        $filter = $this->filter();
        $filter->setSelect($before);
        $filter->apply('   ');

        $this->assertSame($statement, $before->getStatement());
        $this->assertSame([], $before->getBindValues());
    }

    /** Токени коротші за два символи не фільтрують нічого, крім шуму. */
    public function testSingleCharacterTokensAreIgnored(): void
    {
        $select = $this->applyKeyword('a delonghi');

        $this->assertArrayNotHasKey('ps_w_1_0_name', $select->getBindValues());
        $this->assertSame('%delonghi%', $select->getBindValues()['ps_w_0_0_name'] ?? null);
    }

    private function applyKeyword(string $keyword): Select
    {
        $select = $this->select();

        $filter = $this->filter();
        $filter->setSelect($select);
        $filter->apply($keyword);

        return $select;
    }

    private function select(): Select
    {
        // Конструктор AbstractQuery тягне Database із ServiceLocator — заради
        // самого лише debug(), який тут не викликається. У CI бази немає, і
        // контейнер падав ще на резолві, хоча запит нікуди не йде. Тому
        // об'єкт створюється без конструктора, а обгортці підставляється
        // справжній aura-запит: усі методи делегують саме в нього.
        $select = (new \ReflectionClass(Select::class))->newInstanceWithoutConstructor();
        $reflected = new \ReflectionProperty(Select::class, 'queryObject');
        if (PHP_VERSION_ID < 80100) { $reflected->setAccessible(true); }
        $reflected->setValue($select, (new AuraQueryFactory('mysql'))->newSelect());

        $select->from('__products AS p')->cols(['p.id']);

        return $select;
    }

    private function filter(): LexicalProductFilter
    {
        // apply() тягне сервіси через ServiceLocator і кешує їх у статиці —
        // заповнюємо кеш заздалегідь, щоб контейнер тесту не знадобився.
        $reflected = new \ReflectionProperty(LexicalProductFilter::class, 'filterServices');
        if (PHP_VERSION_ID < 80100) { $reflected->setAccessible(true); }
        $reflected->setValue(null, [
            Languages::class => $this->languages(),
            QueryFactory::class => null,
            QueryNormalizer::class => new QueryNormalizer(),
            SearchTransliteration::class => new SearchTransliteration(),
        ]);

        return new LexicalProductFilter();
    }

    private function languages(): Languages
    {
        return new class extends Languages {
            public function __construct()
            {
            }

            public function getLangAlias($tableAlias, $params = [])
            {
                return 'lp';
            }

            public function getLangId()
            {
                return 3;
            }
        };
    }

    private function featureSubquery(string $statement): string
    {
        $start = strpos($statement, 'SELECT product_id FROM __products_features_values');
        $this->assertIsInt($start, 'підзапит за характеристиками не знайдено');

        $end = strpos($statement, '))', $start);
        $this->assertIsInt($end);

        return substr($statement, $start, $end - $start);
    }
}
