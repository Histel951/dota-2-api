<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Tests\Unit\Infrastructure\OpenDota\Extractor;

use Histel951\Dota2Api\Infrastructure\OpenDota\Extractor\MatchExtractor;
use PHPUnit\Framework\TestCase;

class MatchExtractorTest extends TestCase
{
    private const string FIXTURE = __DIR__ . '/../../../../Fixtures/opendota/match_8794744726.json';

    public function test_extractReadsPositionEst()
    {
        $extracted = (new MatchExtractor())->extract(file_get_contents(self::FIXTURE));

        $positions = array_column($extracted['players'], 'position_est', 'account_id');

        $this->assertSame(1, $positions[898754153]);
        $this->assertSame(5, $positions[94296097]);
        $this->assertSame(4, $positions[150961567]);
    }

    public function test_extractReturnsNullForPlayerWithoutOptionalKeys()
    {
        // нераспарсенный матч: position_est (и lane) отсутствуют; phpunit падает на warning
        $data = json_decode(file_get_contents(self::FIXTURE), true);
        foreach ($data['players'] as &$player) {
            unset($player['position_est'], $player['lane'], $player['lane_role']);
        }
        unset($player);

        $extracted = (new MatchExtractor())->extract(json_encode($data));

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
        $data = json_decode(file_get_contents(self::FIXTURE), true);
        $data['players'] = [[]];

        $extracted = (new MatchExtractor())->extract(json_encode($data));

        $this->assertNull($extracted['players'][0]['position_est']);
        $this->assertNull($extracted['players'][0]['account_id']);
    }
}
