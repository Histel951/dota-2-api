<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Tests\Unit\Stratz\Infrastructure\Team;

use Histel951\Dota2Api\Exception\SourceDataException;
use Histel951\Dota2Api\SharedKernel\Side;
use Histel951\Dota2Api\Stratz\Infrastructure\Team\TeamMatchMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TeamMatchMapperTest extends TestCase
{
    private const int TEAM_ID = 8261500;

    private TeamMatchMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new TeamMatchMapper();
    }

    public function test_mapsAllFixtureMatches()
    {
        $rows = $this->rows();

        $matches = $this->mapper->toCollection($rows);

        $this->assertCount(20, $matches);
        foreach ($matches as $i => $match) {
            $row = $rows[$i];
            $isRadiant = $row['radiantTeamId'] === self::TEAM_ID;

            $this->assertSame($row['id'], $match->getMatchId()->getValue());
            $this->assertSame($isRadiant ? Side::RADIANT : Side::DIRE, $match->getSide());
            $this->assertSame($isRadiant === $row['didRadiantWin'], $match->isWon());
            $this->assertSame(
                (string) ($isRadiant ? $row['direTeamId'] : $row['radiantTeamId']),
                $match->getOpposingTeamId()->getValue(),
            );
            $this->assertSame(
                ($isRadiant ? $row['direTeam'] : $row['radiantTeam'])['name'],
                $match->getOpposingTeamName()?->getValue(),
            );
            $this->assertSame($row['durationSeconds'], $match->getDuration()->getSeconds());
            $this->assertSame($row['startDateTime'], $match->getStartTime()->getTimestamp());
            $this->assertSame($row['clusterId'], $match->getCluster()->getValue());
            $this->assertSame($row['leagueId'], $match->getLeagueId()->getValue());
            $this->assertNull($match->getLeagueName());
        }
    }

    public function test_firstMatchIsRadiantWinWithScore()
    {
        $match = $this->mapper->toEntity($this->rows()[0]);

        $this->assertSame(Side::RADIANT, $match->getSide());
        $this->assertTrue($match->isWon());
        $this->assertSame(24, $match->getScore()?->getRadiantKills());
        $this->assertSame(14, $match->getScore()?->getDireKills());
        $this->assertSame('5017210', $match->getOpposingTeamId()->getValue());
        $this->assertSame('EHOME.immortal', $match->getOpposingTeamName()?->getValue());
    }

    public function test_direSideWinIsMapped()
    {
        // 9016550276: radiant GamerLegion, XG за dire, didRadiantWin=false -> XG победила
        $row = $this->rowById(9016550276);
        $this->assertFalse($row['didRadiantWin']);

        $match = $this->mapper->toEntity($row);

        $this->assertSame(Side::DIRE, $match->getSide());
        $this->assertTrue($match->isWon());
        $this->assertSame('GamerLegion', $match->getOpposingTeamName()?->getValue());
        $this->assertSame('9964962', $match->getOpposingTeamId()->getValue());
    }

    public function test_direSideLossIsMapped()
    {
        // 9026055036: radiant LGD, XG за dire, didRadiantWin=true -> XG проиграла
        $match = $this->mapper->toEntity($this->rowById(9026055036));

        $this->assertSame(Side::DIRE, $match->getSide());
        $this->assertFalse($match->isWon());
    }

    public function test_radiantLossIsMapped()
    {
        $match = $this->mapper->toEntity($this->rowById(9017299527));

        $this->assertSame(Side::RADIANT, $match->getSide());
        $this->assertFalse($match->isWon());
    }

    public function test_scoreIsNullWhenKillsAreNull()
    {
        $row = $this->rowById(9049014116);
        $this->assertNull($row['radiantKills']);

        $this->assertNull($this->mapper->toEntity($row)->getScore());
    }

    public function test_nonEmptyLeagueDisplayNameBecomesLeagueName()
    {
        $row = ['league' => ['displayName' => 'The International']] + $this->rows()[0];

        $this->assertSame('The International', $this->mapper->toEntity($row)->getLeagueName()?->getValue());
    }

    #[DataProvider('blankLeagueProvider')]
    public function test_blankLeagueBecomesNull(mixed $league)
    {
        $row = ['league' => $league] + $this->rows()[0];

        $this->assertNull($this->mapper->toEntity($row)->getLeagueName());
    }

    public static function blankLeagueProvider(): array
    {
        return [
            'null' => [null],
            'no name' => [['displayName' => null]],
            'empty name' => [['displayName' => '  ']],
        ];
    }

    public function test_teamNotInMatchThrows()
    {
        $this->expectException(SourceDataException::class);
        $this->expectExceptionMessage('is not a side of match');

        $this->mapper->toEntity(['teamId' => 1] + $this->rows()[0]);
    }

    #[DataProvider('requiredProvider')]
    public function test_missingRequiredFieldThrows(string $field)
    {
        $row = $this->rows()[0];
        unset($row[$field]);

        $this->expectException(SourceDataException::class);

        $this->mapper->toEntity($row);
    }

    public static function requiredProvider(): array
    {
        return [
            ['id'], ['didRadiantWin'], ['durationSeconds'], ['startDateTime'],
            ['clusterId'], ['leagueId'], ['teamId'],
        ];
    }

    public function test_negativeKillsThrowSourceDataException()
    {
        $this->expectException(SourceDataException::class);

        $this->mapper->toEntity(['radiantKills' => [-1]] + $this->rows()[0]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(): array
    {
        $json = json_decode(
            file_get_contents(__DIR__ . '/../../../../Fixtures/stratz/team_8261500_matches.json'),
            true,
        );

        return array_map(
            static fn (array $row): array => ['teamId' => self::TEAM_ID] + $row,
            $json['data']['team']['matches'],
        );
    }

    private function rowById(int $id): array
    {
        foreach ($this->rows() as $row) {
            if ($row['id'] === $id) {
                return $row;
            }
        }

        $this->fail('Match not in fixture: ' . $id);
    }
}
