<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Tests\Unit\Infrastructure\OpenDota\Mapper;

use Histel951\Dota2Api\Domain\Entities\TeamPlayer\TeamPlayer;
use Histel951\Dota2Api\Exception\SourceDataException;
use Histel951\Dota2Api\Infrastructure\OpenDota\Mapper\TeamPlayerOpenDotaMapper;
use PHPUnit\Framework\TestCase;

/**
 * Регрессия: у бывших игроков команды OpenDota отдаёт name = null и is_current_team_member = null.
 */
class TeamPlayerOpenDotaMapperTest extends TestCase
{
    private const string FIXTURE = __DIR__ . '/../../../../Fixtures/opendota/team_8261500_players.json';

    private static function load(): array
    {
        return json_decode((string) file_get_contents(self::FIXTURE), true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_toCollectionMapsWholeFixtureIncludingNullNames()
    {
        $raw = self::load();

        $players = (new TeamPlayerOpenDotaMapper())->toCollection($raw);

        $this->assertCount(38, $players);
        $this->assertContainsOnlyInstancesOf(TeamPlayer::class, $players);
    }

    public function test_toEntityMapsPlayerWithNullNameAndNullMembershipFromFixture()
    {
        $raw = null;
        foreach (self::load() as $item) {
            if ($item['name'] === null) {
                $raw = $item;
                break;
            }
        }
        $this->assertNotNull($raw);
        $this->assertNull($raw['is_current_team_member']);

        $player = (new TeamPlayerOpenDotaMapper())->toEntity($raw);

        $this->assertNull($player->getPlayerProName());
        $this->assertFalse($player->isCurrentTeamMember());
        $this->assertSame(135492698, $player->getPlayerId()->toInt());
        $this->assertSame(150, $player->getPlayerStats()->getGamesPlayed());
        $this->assertSame(65, $player->getPlayerStats()->getGamesWon());
    }

    public function test_toEntityKeepsNameAndTrueMembershipFromFixture()
    {
        $player = (new TeamPlayerOpenDotaMapper())->toEntity(self::load()[0]);

        $this->assertSame('Ame', $player->getPlayerProName()?->getValue());
        $this->assertTrue($player->isCurrentTeamMember());
        $this->assertSame(898754153, $player->getPlayerId()->toInt());
        $this->assertSame(673, $player->getPlayerStats()->getGamesPlayed());
        $this->assertSame(381, $player->getPlayerStats()->getGamesWon());
    }

    public function test_toEntityKeepsNameAndFalseMembershipFromFixture()
    {
        $player = (new TeamPlayerOpenDotaMapper())->toEntity(self::load()[1]);

        $this->assertSame('Xxs', $player->getPlayerProName()?->getValue());
        $this->assertFalse($player->isCurrentTeamMember());
    }

    public function test_toEntityAcceptsMissingOptionalFields()
    {
        $player = (new TeamPlayerOpenDotaMapper())->toEntity([
            'account_id' => 1234567,
            'games_played' => 10,
            'wins' => 4,
        ]);

        $this->assertNull($player->getPlayerProName());
        $this->assertFalse($player->isCurrentTeamMember());
    }

    public function test_toEntityThrowsSourceDataExceptionWhenAccountIdMissing()
    {
        $this->expectException(SourceDataException::class);

        (new TeamPlayerOpenDotaMapper())->toEntity([
            'name' => 'Ame',
            'games_played' => 10,
            'wins' => 4,
            'is_current_team_member' => true,
        ]);
    }
}
