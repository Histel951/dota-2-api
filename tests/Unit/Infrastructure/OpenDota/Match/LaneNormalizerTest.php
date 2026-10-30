<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Tests\Unit\Infrastructure\OpenDota\Match;

use Histel951\Dota2Api\Infrastructure\OpenDota\Match\LaneNormalizer;
use Histel951\Dota2Api\Match\Domain\Player\Lane;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LaneNormalizerTest extends TestCase
{
    #[DataProvider('laneProvider')]
    public function test_normalizeReturnsLaneFromRadiantSide(?int $lane, ?int $laneRole, bool $isRadiant, Lane $expected)
    {
        $this->assertSame($expected, (new LaneNormalizer())->normalize($lane, $laneRole, $isRadiant));
    }

    public static function laneProvider(): array
    {
        // линия возвращается со стороны radiant: safe lane dire — это Lane::OFFLANE
        return [
            'lane 1 radiant' => [1, 1, true, Lane::SAFE],
            'lane 2 radiant' => [2, 2, true, Lane::MIDDLE],
            'lane 3 radiant' => [3, 3, true, Lane::OFFLANE],
            'lane 1 dire is taken as is' => [1, 3, false, Lane::SAFE],
            'lane 3 dire is taken as is' => [3, 1, false, Lane::OFFLANE],
            'radiant safe' => [4, 1, true, Lane::SAFE],
            'radiant middle' => [4, 2, true, Lane::MIDDLE],
            'radiant offlane' => [4, 3, true, Lane::OFFLANE],
            'radiant jungle' => [4, 4, true, Lane::SAFE],
            'dire safe' => [5, 1, false, Lane::OFFLANE],
            'dire middle' => [5, 2, false, Lane::MIDDLE],
            'dire offlane' => [5, 3, false, Lane::SAFE],
            'dire jungle' => [5, 4, false, Lane::OFFLANE],
            'unparsed radiant without lane' => [null, null, true, Lane::SAFE],
            'unparsed dire without lane' => [null, null, false, Lane::OFFLANE],
            'unparsed radiant with lane role' => [null, 3, true, Lane::OFFLANE],
            'unparsed dire with lane role' => [null, 3, false, Lane::SAFE],
            'unparsed middle' => [null, 2, false, Lane::MIDDLE],
            'lane zero radiant' => [0, null, true, Lane::SAFE],
            'unknown lane value dire' => [9, 4, false, Lane::OFFLANE],
        ];
    }
}
