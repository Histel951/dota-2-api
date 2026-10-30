<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Tests\Unit\Stratz\Infrastructure\Match;

use Histel951\Dota2Api\SharedKernel\MatchId;
use Histel951\Dota2Api\Stratz\Infrastructure\Match\MatchQuery;
use PHPUnit\Framework\TestCase;

class MatchQueryTest extends TestCase
{
    public function test_buildSingleMatch()
    {
        $built = MatchQuery::build([new MatchId(9026055036)]);

        $this->assertSame(['m0' => 9026055036], $built['variables']);
        $this->assertStringContainsString('query Matches($m0: Long!)', $built['query']);
        $this->assertStringContainsString('m0: match(id: $m0) { ...MatchFields }', $built['query']);
        $this->assertStringContainsString(MatchQuery::FIELDS, $built['query']);
    }

    public function test_buildContainsRequestedFields()
    {
        $query = MatchQuery::build([new MatchId(1)])['query'];

        foreach (['fragment MatchFields on MatchType', 'pickBans', 'playbackData', 'buyBackEvents', 'stunDuration'] as $needle) {
            $this->assertStringContainsString($needle, $query);
        }
    }

    public function test_buildUsesIdsAsLongVariables()
    {
        $this->assertSame(5, MatchQuery::build([new MatchId(5)])['variables']['m0']);
    }

    public function test_buildWithSeveralIdsGivesDistinctAliases()
    {
        // BATCH_SIZE ограничивает пачку на стороне источника, сам build собирает любое число id;
        // дубли получают разные алиасы
        $built = MatchQuery::build([new MatchId(10), new MatchId(20), new MatchId(10)]);

        $this->assertSame(['m0' => 10, 'm1' => 20, 'm2' => 10], $built['variables']);
        $this->assertStringContainsString('query Matches($m0: Long!, $m1: Long!, $m2: Long!)', $built['query']);
        foreach (['m0', 'm1', 'm2'] as $alias) {
            $this->assertStringContainsString(sprintf('%1$s: match(id: $%1$s) { ...MatchFields }', $alias), $built['query']);
        }
        $this->assertSame(1, substr_count($built['query'], 'fragment MatchFields'));
    }

    public function test_aliasIsPrefixedIndex()
    {
        $this->assertSame('m0', MatchQuery::alias(0));
        $this->assertSame('m12', MatchQuery::alias(12));
    }

    public function test_batchSizeIsPositive()
    {
        $this->assertGreaterThanOrEqual(1, MatchQuery::BATCH_SIZE);
    }
}
