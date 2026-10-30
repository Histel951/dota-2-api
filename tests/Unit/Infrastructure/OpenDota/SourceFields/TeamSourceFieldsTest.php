<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Tests\Unit\Infrastructure\OpenDota\SourceFields;

use Histel951\Dota2Api\Tests\Support\OpenDota\SourceFieldsChecker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TeamSourceFieldsTest extends TestCase
{
    private const string FIXTURES = __DIR__ . '/../../../../Fixtures/opendota/';

    /**
     * @return array<string, array{string, string}>
     */
    public static function fixturesProvider(): array
    {
        return [
            'teams' => ['teams.json', 'checkTeams'],
            'team' => ['team_8261500.json', 'checkTeam'],
            'team matches' => ['team_8261500_matches.json', 'checkTeamMatches'],
            'team players' => ['team_8261500_players.json', 'checkTeamPlayers'],
            'team heroes' => ['team_8261500_heroes.json', 'checkTeamHeroes'],
        ];
    }

    #[DataProvider('fixturesProvider')]
    public function test_fixtureContainsAllFieldsExpectedByCode(string $fixture, string $checker)
    {
        $data = json_decode((string) file_get_contents(self::FIXTURES . $fixture), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame([], SourceFieldsChecker::$checker($data));
    }
}
