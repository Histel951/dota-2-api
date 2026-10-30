<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Tests\Unit\Stratz\Infrastructure\Team;

use Histel951\Dota2Api\Exception\SourceDataException;
use Histel951\Dota2Api\Stratz\Infrastructure\Team\TeamHeroMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TeamHeroMapperTest extends TestCase
{
    private TeamHeroMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new TeamHeroMapper();
    }

    public function test_mapsAllFixtureHeroes()
    {
        $heroes = $this->mapper->toCollection($this->rows());

        $this->assertCount(107, $heroes);
        $this->assertSame(155, $heroes[0]->getHeroId()->getValue());
        $this->assertSame('Largo', $heroes[0]->getName()->getValue());
        $this->assertSame(13, $heroes[0]->getStats()->getGamesPlayed());
        $this->assertSame(5, $heroes[0]->getStats()->getGamesWon());
        $this->assertSame(38.46, $heroes[0]->getStats()->getWinrate());
        $this->assertSame('Kez', $heroes[1]->getName()->getValue());
    }

    public function test_heroWithoutWinsHasZeroWinrate()
    {
        $hero = $this->mapper->toEntity(['heroId' => 121, 'matchCount' => 1, 'winCount' => 0, 'displayName' => 'Axe']);

        $this->assertSame(0.0, $hero->getStats()->getWinrate());
        $this->assertSame(1, $hero->getStats()->getGamesLost());
    }

    public function test_nullDisplayNameThrows()
    {
        $this->expectException(SourceDataException::class);

        $this->mapper->toEntity(['displayName' => null] + $this->rows()[0]);
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
        return [['heroId'], ['matchCount'], ['winCount'], ['displayName']];
    }

    public function test_winsAboveGamesThrowSourceDataException()
    {
        $this->expectException(SourceDataException::class);

        $this->mapper->toEntity(['winCount' => 99] + $this->rows()[0]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(): array
    {
        $data = json_decode(
            file_get_contents(__DIR__ . '/../../../../Fixtures/stratz/team_8261500_heroes.json'),
            true,
        )['data'];

        $names = array_column($data['constants']['heroes'], 'displayName', 'id');

        return array_map(
            static fn (array $row): array => $row + ['displayName' => $names[$row['heroId']] ?? null],
            $data['team']['heroes'],
        );
    }
}
