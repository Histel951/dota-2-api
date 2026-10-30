<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Tests\Unit\Stratz\Infrastructure\Match;

use Histel951\Dota2Api\Stratz\Infrastructure\Match\LaneMapper;
use Histel951\Dota2Api\Stratz\Match\Domain\Player\Lane;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LaneMapperTest extends TestCase
{
    #[DataProvider('lanesProvider')]
    public function test_toLane(?string $raw, Lane $expected)
    {
        $this->assertSame($expected, (new LaneMapper())->toLane($raw));
    }

    /**
     * @return array<string, array{?string, Lane}>
     */
    public static function lanesProvider(): array
    {
        return [
            'safe' => ['SAFE_LANE', Lane::SAFE],
            'mid' => ['MID_LANE', Lane::MIDDLE],
            'off' => ['OFF_LANE', Lane::OFF],
            'jungle' => ['JUNGLE', Lane::JUNGLE],
            'roaming' => ['ROAMING', Lane::ROAMING],
            'null' => [null, Lane::UNKNOWN],
            'unknownValue' => ['SOMETHING_NEW', Lane::UNKNOWN],
            'empty' => ['', Lane::UNKNOWN],
            'lowercase' => ['safe_lane', Lane::UNKNOWN],
        ];
    }
}
