<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Tests\Unit\Infrastructure\OpenDota\Mapper;

use Histel951\Dota2Api\Domain\Entities\TeamHero\TeamHero;
use Histel951\Dota2Api\Exception\SourceDataException;
use Histel951\Dota2Api\Infrastructure\OpenDota\Mapper\TeamHeroOpenDotaMapper;
use PHPUnit\Framework\TestCase;

class TeamHeroOpenDotaMapperTest extends TestCase
{
    private const string FIXTURE = __DIR__ . '/../../../../Fixtures/opendota/team_8261500_heroes.json';

    private static function load(): array
    {
        return json_decode((string) file_get_contents(self::FIXTURE), true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_toCollectionMapsAllHeroesFromFixture()
    {
        $raw = self::load();

        $heroes = (new TeamHeroOpenDotaMapper())->toCollection($raw);

        $this->assertCount(count($raw), $heroes);
        $this->assertContainsOnlyInstancesOf(TeamHero::class, $heroes);

        $first = $heroes[0];
        $this->assertSame(17, $first->getId()->getValue());
        $this->assertSame('Storm Spirit', $first->getName()->getValue());
        $this->assertSame(146, $first->getStats()->getGamesPlayed());
        $this->assertSame(83, $first->getStats()->getGamesWon());
    }

    public function test_toEntityThrowsSourceDataExceptionWhenRequiredFieldMissing()
    {
        $data = self::load()[0];
        unset($data['localized_name']);

        $this->expectException(SourceDataException::class);

        (new TeamHeroOpenDotaMapper())->toEntity($data);
    }
}
