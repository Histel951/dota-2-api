<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Tests\Unit\Infrastructure\OpenDota\Match;

use Histel951\Dota2Api\Infrastructure\OpenDota\Match\MatchExtractor;
use PHPUnit\Framework\TestCase;

class MatchExtractorTest extends TestCase
{
    private const string FIXTURE = __DIR__ . '/../../../../Fixtures/opendota/match_8794744726.json';

    public function test_extractReadsPositionEst()
    {
        $extracted = (new MatchExtractor())->extract($this->fixture());

        $positions = array_column($extracted['players'], 'position_est', 'account_id');

        $this->assertSame(1, $positions[898754153]);
        $this->assertSame(5, $positions[94296097]);
        $this->assertSame(4, $positions[150961567]);
    }

    public function test_extractReadsMatchFields()
    {
        $extracted = (new MatchExtractor())->extract($this->fixture());

        $this->assertSame(8794744726, $extracted['match_id']);
        $this->assertSame(2776, $extracted['duration']);
        $this->assertFalse($extracted['radiant_win']);
        $this->assertSame(1777714862, $extracted['start_time']);
        $this->assertSame(8261500, $extracted['radiant_team_id']);
        $this->assertSame('Team Resilience', $extracted['dire_name']);
        $this->assertSame(13, $extracted['region']);
        $this->assertCount(24, $extracted['picks_bans']);
        $this->assertCount(10, $extracted['players']);
    }

    public function test_extractDropsFieldsOutsideWhitelist()
    {
        $extracted = (new MatchExtractor())->extract($this->fixture());

        $this->assertArrayNotHasKey('teamfights', $extracted);
        $this->assertArrayNotHasKey('version', $extracted);
        $this->assertArrayNotHasKey('benchmarks', $extracted['players'][0]);
    }

    public function test_extractReturnsNullForPlayerWithoutOptionalKeys()
    {
        // нераспарсенный матч: position_est (и lane) отсутствуют; phpunit падает на warning
        $data = $this->fixture();
        foreach ($data['players'] as &$player) {
            unset($player['position_est'], $player['lane'], $player['lane_role']);
        }
        unset($player);

        $extracted = (new MatchExtractor())->extract($data);

        $this->assertCount(10, $extracted['players']);
        foreach ($extracted['players'] as $player) {
            $this->assertArrayHasKey('position_est', $player);
            $this->assertNull($player['position_est']);
            $this->assertNull($player['lane']);
            $this->assertNull($player['lane_role']);
        }
    }

    public function test_extractReturnsNullForPlayerWithoutAnyKeys()
    {
        $data = $this->fixture();
        $data['players'] = [[]];

        $extracted = (new MatchExtractor())->extract($data);

        $this->assertNull($extracted['players'][0]['position_est']);
        $this->assertNull($extracted['players'][0]['account_id']);
    }

    public function test_extractTreatsNonArrayPlayerAsEmpty()
    {
        $data = $this->fixture();
        $data['players'] = ['garbage'];

        $extracted = (new MatchExtractor())->extract($data);

        $this->assertCount(1, $extracted['players']);
        $this->assertNull($extracted['players'][0]['account_id']);
    }

    public function test_extractGarbageArrayReturnsNullsWithoutWarnings()
    {
        $extracted = (new MatchExtractor())->extract(['a' => 1]);

        $this->assertNull($extracted['players']);
        $this->assertNull($extracted['match_id']);
        $this->assertNull($extracted['duration']);
        $this->assertNull($extracted['radiant_win']);
        $this->assertNull($extracted['picks_bans']);
        $this->assertArrayNotHasKey('a', $extracted);
    }

    public function test_extractEmptyArrayReturnsNulls()
    {
        $extracted = (new MatchExtractor())->extract([]);

        $this->assertNull($extracted['players']);
        $this->assertNull($extracted['match_id']);
    }

    public function test_extractNonArrayPlayersBecomeNull()
    {
        $extracted = (new MatchExtractor())->extract(['players' => 'oops']);

        $this->assertNull($extracted['players']);
    }

    private function fixture(): array
    {
        return json_decode(file_get_contents(self::FIXTURE), true);
    }
}
