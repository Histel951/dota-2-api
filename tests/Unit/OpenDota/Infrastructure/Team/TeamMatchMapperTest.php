<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Tests\Unit\OpenDota\Infrastructure\Team;

use Histel951\Dota2Api\Exception\SourceDataException;
use Histel951\Dota2Api\OpenDota\Infrastructure\Team\TeamMatchMapper;
use Histel951\Dota2Api\OpenDota\Team\Domain\History\TeamMatch;
use PHPUnit\Framework\TestCase;

class TeamMatchMapperTest extends TestCase
{
    private const string FIXTURE = __DIR__ . '/../../../../Fixtures/opendota/team_8261500_matches.json';

    private static function load(): array
    {
        return json_decode((string) file_get_contents(self::FIXTURE), true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_toCollectionMapsAllMatchesFromFixture()
    {
        $raw = self::load();

        $matches = (new TeamMatchMapper())->toCollection($raw);

        $this->assertCount(count($raw), $matches);
        $this->assertContainsOnlyInstancesOf(TeamMatch::class, $matches);
    }

    public function test_toEntityMapsFirstMatchAsDireLoss()
    {
        $match = (new TeamMatchMapper())->toEntity(self::load()[0]);

        $this->assertSame(9026055036, $match->getMatchId()->getValue());
        $this->assertSame(19102, $match->getLeagueId()->getValue());
        $this->assertSame('BLAST SLAM VIII', $match->getLeagueName()->getValue());
        $this->assertSame(273, $match->getMatchCluster()->getValue());
        $this->assertSame(15, $match->getOpposingTeamId()->toInt());
        $this->assertSame('LGD Gaming', $match->getOpposingTeamName()?->getValue());
        $this->assertSame(1790954533, $match->getStartTime()->getTimestamp());

        // radiant=false, radiant_win=true: команда играла за Dire и проиграла
        $outcome = $match->matchOutcome();
        $this->assertTrue($outcome->isDire());
        $this->assertTrue($outcome->isLoss());
        $this->assertSame(26, $outcome->getRadiantKills());
        $this->assertSame(30, $outcome->getDireKills());
    }

    public function test_toEntityMapsSecondMatchAsRadiantWin()
    {
        $match = (new TeamMatchMapper())->toEntity(self::load()[1]);

        $this->assertTrue($match->matchOutcome()->isRadiant());
        $this->assertTrue($match->matchOutcome()->isWon());
    }

    public function test_toEntityThrowsSourceDataExceptionWhenRequiredFieldMissing()
    {
        $data = self::load()[0];
        unset($data['league_name']);

        $this->expectException(SourceDataException::class);

        (new TeamMatchMapper())->toEntity($data);
    }
}
