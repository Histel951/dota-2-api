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
        $built = MatchQuery::build([new MatchId(9026055036)], true);

        $this->assertSame(['m0' => 9026055036], $built['variables']);
        $this->assertStringContainsString('query Matches($m0: Long!)', $built['query']);
        $this->assertStringContainsString('m0: match(id: $m0) { ...MatchFields }', $built['query']);
        $this->assertStringContainsString(MatchQuery::FIELDS, $built['query']);
    }

    public function test_buildContainsRequestedFields()
    {
        $query = MatchQuery::build([new MatchId(1)], true)['query'];

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
        // batchSize() ограничивает пачку на стороне источника, сам build собирает любое число id;
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

    public function test_buildWithoutPlaybackDataByDefault()
    {
        $this->assertSame(MatchQuery::build([new MatchId(1)]), MatchQuery::build([new MatchId(1)], false));
        $this->assertStringNotContainsString('playbackData', MatchQuery::build([new MatchId(1)])['query']);
    }

    public function test_buildWithoutPlaybackDataOmitsPlaybackFields()
    {
        $query = MatchQuery::build([new MatchId(1), new MatchId(2)], false)['query'];

        $this->assertStringNotContainsString('playbackData', $query);
        $this->assertStringNotContainsString('buyBackEvents', $query);
        // остальные поля фрагмента на месте
        foreach (['fragment MatchFields on MatchType', 'pickBans', 'stunDuration', 'position', 'farmDistributionReport'] as $needle) {
            $this->assertStringContainsString($needle, $query);
        }
        $this->assertStringContainsString('m1: match(id: $m1) { ...MatchFields }', $query);
    }

    public function test_fragmentDiffersOnlyByPlaybackFields()
    {
        $this->assertStringContainsString(MatchQuery::PLAYBACK_FIELDS, MatchQuery::FIELDS);
        $this->assertSame(MatchQuery::FIELDS, MatchQuery::fragment(true));
        $this->assertSame(
            str_replace(MatchQuery::PLAYBACK_FIELDS, '', MatchQuery::FIELDS),
            MatchQuery::fragment(false),
        );
    }

    public function test_batchSize()
    {
        $this->assertSame(1, MatchQuery::BATCH_SIZE_WITH_PLAYBACK);
        $this->assertSame(5, MatchQuery::BATCH_SIZE);
        $this->assertSame(MatchQuery::BATCH_SIZE_WITH_PLAYBACK, MatchQuery::batchSize(true));
        $this->assertSame(MatchQuery::BATCH_SIZE, MatchQuery::batchSize(false));
    }
}
