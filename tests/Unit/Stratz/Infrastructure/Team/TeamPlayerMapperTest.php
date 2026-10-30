<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Tests\Unit\Stratz\Infrastructure\Team;

use Histel951\Dota2Api\Exception\SourceDataException;
use Histel951\Dota2Api\Stratz\Infrastructure\Team\TeamPlayerMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TeamPlayerMapperTest extends TestCase
{
    private const int TEAM_ID = 8261500;

    private TeamPlayerMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new TeamPlayerMapper();
    }

    public function test_mapsAllFixturePlayers()
    {
        $players = $this->mapper->toCollection($this->rows());

        $this->assertCount(11, $players);
        $this->assertSame('898754153', $players[0]->getPlayerId()->getValue());
        $this->assertSame('Ame', $players[0]->getName()?->getValue());
        $this->assertSame(100, $players[0]->getStats()->getGamesPlayed());
        $this->assertSame(46, $players[0]->getStats()->getGamesWon());
    }

    public function test_currentRosterIsAmeXinQXmXxs()
    {
        $current = [];
        foreach ($this->mapper->toCollection($this->rows()) as $player) {
            if ($player->isCurrentMember()) {
                $current[] = $player->getName()?->getValue();
            }
        }

        $this->assertEqualsCanonicalizing(['Ame', 'XinQ', 'Xm', 'Xxs'], $current);
    }

    public function test_playerOfAnotherTeamIsFormer()
    {
        $player = $this->mapper->toEntity($this->rowBySteamId(207829314));

        $this->assertFalse($player->isCurrentMember());
        $this->assertSame('Borax', $player->getName()?->getValue());
    }

    public function test_teamIdZeroIsFormer()
    {
        $this->assertFalse($this->mapper->toEntity($this->rowBySteamId(113457795))->isCurrentMember());
    }

    public function test_nameFallsBackToSteamAccountNameWithoutProAccount()
    {
        $player = $this->mapper->toEntity($this->rowBySteamId(98887913));

        $this->assertSame('TIGER', $player->getName()?->getValue());
        $this->assertFalse($player->isCurrentMember());
    }

    public function test_nameIsNullWithoutAnyName()
    {
        $row = ['steamAccount' => null] + $this->rowBySteamId(98887913);

        $this->assertNull($this->mapper->toEntity($row)->getName());
    }

    public function test_blankNameIsNull()
    {
        $row = ['steamAccount' => ['name' => '  ', 'proSteamAccount' => null]] + $this->rowBySteamId(98887913);

        $this->assertNull($this->mapper->toEntity($row)->getName());
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
        return [['steamAccountId'], ['matchCount'], ['winCount'], ['teamId']];
    }

    public function test_winsAboveGamesThrowSourceDataException()
    {
        $this->expectException(SourceDataException::class);

        $this->mapper->toEntity(['winCount' => 101] + $this->rows()[0]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(): array
    {
        $json = json_decode(
            file_get_contents(__DIR__ . '/../../../../Fixtures/stratz/team_8261500_players.json'),
            true,
        );

        return array_map(
            static fn (array $row): array => ['teamId' => self::TEAM_ID] + $row,
            $json['data']['team']['players'],
        );
    }

    private function rowBySteamId(int $id): array
    {
        foreach ($this->rows() as $row) {
            if ($row['steamAccountId'] === $id) {
                return $row;
            }
        }

        $this->fail('Player not in fixture: ' . $id);
    }
}
