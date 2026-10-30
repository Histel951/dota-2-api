<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Tests\Unit\Infrastructure\OpenDota\Mapper;

use Histel951\Dota2Api\Domain\Entities\Team\Team;
use Histel951\Dota2Api\Exception\SourceDataException;
use Histel951\Dota2Api\Infrastructure\OpenDota\Mapper\TeamOpenDotaMapper;
use PHPUnit\Framework\TestCase;

class TeamOpenDotaMapperTest extends TestCase
{
    private const string FIXTURES = __DIR__ . '/../../../../Fixtures/opendota/';

    private static function load(string $file): array
    {
        return json_decode((string) file_get_contents(self::FIXTURES . $file), true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_toCollectionMapsAllTeamsFromFixture()
    {
        $raw = self::load('teams.json');

        $teams = (new TeamOpenDotaMapper())->toCollection($raw);

        $this->assertCount(count($raw), $teams);
        $this->assertContainsOnlyInstancesOf(Team::class, $teams);

        $first = $teams[0];
        $this->assertSame(9823272, $first->getId()->toInt());
        $this->assertSame('Team Yandex', $first->getName()?->getValue());
        $this->assertSame('TY', $first->getTag()?->getValue());
        $this->assertSame(231, $first->getStats()->getWins());
        $this->assertSame(142, $first->getStats()->getLosses());
        $this->assertSame(1589.46, $first->getStats()->getRating());
        $this->assertSame(1793295577, $first->getLastMatchTime()->getTimestamp());
    }

    /**
     * Реальный список /teams содержит команду с name = "" и tag = "" (team_id 5026801):
     * пустые name и tag маппятся в null, а не роняют весь список
     */
    public function test_toCollectionMapsRealTeamWithEmptyNameAndTagAsNull()
    {
        $teams = (new TeamOpenDotaMapper())->toCollection(self::load('teams.json'));

        $team = $teams[13];
        $this->assertSame(5026801, $team->getId()->toInt());
        $this->assertNull($team->getTag());
        $this->assertNull($team->getName());
    }

    public function test_toEntityMapsWhitespaceOrMissingNameAsNull()
    {
        $data = self::load('team_8261500.json');
        $data['name'] = '  ';
        $this->assertNull((new TeamOpenDotaMapper())->toEntity($data)->getName());

        unset($data['name']);
        $this->assertNull((new TeamOpenDotaMapper())->toEntity($data)->getName());
    }

    public function test_toEntityMapsWhitespaceTagAsNull()
    {
        $data = self::load('team_8261500.json');
        $data['tag'] = '   ';

        $this->assertNull((new TeamOpenDotaMapper())->toEntity($data)->getTag());
    }

    public function test_toEntityMapsSingleTeamFixture()
    {
        $team = (new TeamOpenDotaMapper())->toEntity(self::load('team_8261500.json'));

        $this->assertSame(8261500, $team->getId()->toInt());
        $this->assertSame('Xtreme Gaming', $team->getName()?->getValue());
        $this->assertSame('XG', $team->getTag()?->getValue());
        $this->assertSame(627, $team->getStats()->getWins());
        $this->assertSame(516, $team->getStats()->getLosses());
    }

    public function test_toEntityThrowsSourceDataExceptionWhenRequiredFieldMissing()
    {
        $data = self::load('team_8261500.json');
        unset($data['tag']);

        $this->expectException(SourceDataException::class);

        (new TeamOpenDotaMapper())->toEntity($data);
    }
}
