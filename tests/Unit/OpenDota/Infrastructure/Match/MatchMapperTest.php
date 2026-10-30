<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Tests\Unit\OpenDota\Infrastructure\Match;

use Histel951\Dota2Api\Exception\SourceDataException;
use Histel951\Dota2Api\OpenDota\Infrastructure\Match\MatchExtractor;
use Histel951\Dota2Api\OpenDota\Infrastructure\Match\MatchMapper;
use Histel951\Dota2Api\OpenDota\Match\Domain\MatchDetail;
use Histel951\Dota2Api\OpenDota\Match\Domain\Player\Lane;
use Histel951\Dota2Api\OpenDota\Match\Domain\Player\MatchPlayerPerformance;
use Histel951\Dota2Api\OpenDota\Match\Domain\Player\PlayerRole;
use Histel951\Dota2Api\OpenDota\Match\Domain\Role\ProSceneRoleResolver;
use Histel951\Dota2Api\SharedKernel\Exception\DomainException;
use Histel951\Dota2Api\SharedKernel\Side;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TypeError;
use ValueError;

class MatchMapperTest extends TestCase
{
    private const string FIXTURE = __DIR__ . '/../../../../Fixtures/opendota/match_8794744726.json';

    private MatchMapper $mapper;
    private MatchExtractor $extractor;

    public function setUp(): void
    {
        $this->mapper = new MatchMapper();
        $this->extractor = new MatchExtractor();
    }

    public function test_toEntityMapsMatchSummary()
    {
        $match = $this->mapFixture();

        $this->assertSame(8794744726, $match->getId()->getValue());
        $this->assertSame(2776, $match->getDuration()->getSeconds());
        $this->assertSame(1777714862, $match->getStartTime()->getValue()->getTimestamp());
        $this->assertSame(13, $match->getRegion()->getId());

        $this->assertSame('8261500', $match->getRadiant()->getTeamId()->getValue());
        $this->assertSame('Xtreme Gaming', $match->getRadiant()->getTeamName()->getValue());
        $this->assertFalse($match->getRadiant()->isWon());

        $this->assertSame('5017210', $match->getDire()->getTeamId()->getValue());
        $this->assertSame('Team Resilience', $match->getDire()->getTeamName()->getValue());
        $this->assertTrue($match->getDire()->isWon());

        $this->assertSame(Side::DIRE, $match->getResult()->getWinner()->getSide());
        $this->assertCount(10, $match->getDraft()->getPicks());
        $this->assertCount(14, $match->getDraft()->getBans());
        $this->assertCount(10, $match->getPlayers()->getValue());
    }

    #[DataProvider('playersProvider')]
    public function test_toEntityMapsPlayer(
        int $accountId,
        Side $side,
        Lane $lane,
        PlayerRole $role,
        array $kda,
        int $gpm,
        int $smokeUses,
        int $tormentorKills,
        int $campsStacked,
        int $sentryPlaced,
    )
    {
        $match = $this->mapFixture()->withResolvedRoles(new ProSceneRoleResolver());
        $player = $this->findPlayer($match, $accountId);

        $this->assertSame($side, $player->getIdentity()->getSide());
        $this->assertSame($lane, $player->getIdentity()->getLane()->getValue());
        $this->assertSame($role, $player->getRole()->getValue());
        $this->assertSame($kda, [
            $player->getKda()->getKills(),
            $player->getKda()->getDeaths(),
            $player->getKda()->getAssists(),
        ]);
        $this->assertSame($gpm, $player->getEconomy()->getGPM()->getValue());
        $this->assertSame($smokeUses, $player->getUtility()->getSmokeUses()->getValue());
        $this->assertSame($tormentorKills, $player->getObjectives()->getTormentorKills()->getValue());
        $this->assertSame($campsStacked, $player->getCreeps()->getCampsStacked()->getValue());
        $this->assertSame($sentryPlaced, $player->getWarding()->getSentryPlaced()->getValue());
    }

    public static function playersProvider(): array
    {
        return [
            'NothingToSay' => [173978074, Side::RADIANT, Lane::MIDDLE, PlayerRole::MIDDLE, [7, 5, 7], 599, 0, 0, 2, 1],
            'Ame' => [898754153, Side::RADIANT, Lane::SAFE, PlayerRole::CARRY, [2, 5, 9], 693, 0, 0, 2, 2],
            'xNova' => [94296097, Side::RADIANT, Lane::SAFE, PlayerRole::HARD_SUPPORT, [2, 9, 10], 280, 4, 0, 0, 30],
            'TIGER' => [98887913, Side::RADIANT, Lane::OFFLANE, PlayerRole::OFFLANE, [4, 9, 4], 479, 0, 0, 1, 0],
            'fy' => [101695162, Side::RADIANT, Lane::OFFLANE, PlayerRole::SUPPORT, [3, 12, 6], 277, 2, 1, 6, 6],
            'Echozz' => [315272623, Side::DIRE, Lane::MIDDLE, PlayerRole::MIDDLE, [5, 1, 22], 596, 0, 0, 2, 1],
            'YSR-04E' => [170896543, Side::DIRE, Lane::OFFLANE, PlayerRole::CARRY, [11, 3, 8], 743, 0, 0, 0, 0],
            'zzq' => [249835593, Side::DIRE, Lane::OFFLANE, PlayerRole::HARD_SUPPORT, [3, 8, 21], 369, 4, 0, 0, 21],
            'niu' => [145957968, Side::DIRE, Lane::SAFE, PlayerRole::OFFLANE, [17, 2, 18], 626, 0, 0, 11, 0],
            'planet' => [150961567, Side::DIRE, Lane::SAFE, PlayerRole::SUPPORT, [2, 4, 25], 327, 4, 1, 6, 14],
        ];
    }

    public function test_toEntityMapsMatchWithoutTeams()
    {
        $match = $this->mapFixture(static function (array $data): array {
            $data['radiant_team_id'] = null;
            $data['radiant_name'] = null;
            $data['dire_team_id'] = null;
            $data['dire_name'] = null;

            return $data;
        });

        $this->assertNull($match->getRadiant()->getTeamId()->getValue());
        $this->assertNull($match->getRadiant()->getTeamName()->getValue());
        $this->assertNull($match->getDire()->getTeamName()->getValue());
    }

    public function test_toEntityMapsMatchWithoutDraftAndRegion()
    {
        $match = $this->mapFixture(static function (array $data): array {
            unset($data['picks_bans'], $data['region']);

            return $data;
        });

        $this->assertSame([], $match->getDraft()->getPicks());
        $this->assertSame([], $match->getDraft()->getBans());
        $this->assertSame(0, $match->getRegion()->getId());
    }

    public function test_toEntitySplitsDraftBySide()
    {
        $match = $this->mapFixture();

        $this->assertCount(5, $match->getRadiant()->getDraft()->getPicks());
        $this->assertCount(5, $match->getDire()->getDraft()->getPicks());
        $this->assertCount(
            14,
            [...$match->getRadiant()->getDraft()->getBans(), ...$match->getDire()->getDraft()->getBans()],
        );
    }

    public function test_toEntityMapsDireSidePlayersToDireTeam()
    {
        $match = $this->mapFixture();

        foreach ($match->getDire()->getPlayers()->getValue() as $player) {
            $this->assertSame(Side::DIRE, $player->getIdentity()->getSide());
        }
        $this->assertCount(5, $match->getRadiant()->getPlayers()->getValue());
        $this->assertCount(5, $match->getDire()->getPlayers()->getValue());
    }

    public function test_toEntityWrapsInvalidValueObjectIntoSourceDataException()
    {
        try {
            $this->mapFixture(static function (array $data): array {
                $data['duration'] = -1;

                return $data;
            });
            $this->fail('SourceDataException expected');
        } catch (SourceDataException $e) {
            $this->assertInstanceOf(DomainException::class, $e->getPrevious());
        }
    }

    public function test_toEntityDoesNotResolveRoles()
    {
        // раскладка линий, на которой падает резолвер: маппер роли не резолвит и матч отдаёт,
        // ошибку даёт withResolvedRoles
        $match = $this->mapFixture(static function (array $data): array {
            foreach ($data['players'] as &$player) {
                $player['lane'] = 1;
                $player['position_est'] = null;
            }

            return $data;
        });

        $this->assertCount(10, $match->getPlayers()->getValue());

        $this->expectException(DomainException::class);
        $match->withResolvedRoles(new ProSceneRoleResolver());
    }

    public function test_toEntityWrapsUnexpectedFieldTypeIntoSourceDataException()
    {
        try {
            $this->mapFixture(static function (array $data): array {
                $data['players'][0]['account_id'] = null;

                return $data;
            });
            $this->fail('SourceDataException expected');
        } catch (SourceDataException $e) {
            $this->assertInstanceOf(TypeError::class, $e->getPrevious());
        }
    }

    public function test_toEntityWrapsUnknownDraftSideIntoSourceDataException()
    {
        try {
            $this->mapFixture(static function (array $data): array {
                $data['picks_bans'][0]['team'] = 7;

                return $data;
            });
            $this->fail('SourceDataException expected');
        } catch (SourceDataException $e) {
            $this->assertInstanceOf(ValueError::class, $e->getPrevious());
        }
    }

    #[DataProvider('requiredFieldProvider')]
    public function test_toEntityFailsOnMissingRequiredField(string $field)
    {
        $this->expectException(SourceDataException::class);
        $this->expectExceptionMessage($field);

        $this->mapFixture(static function (array $data) use ($field): array {
            unset($data[$field]);

            return $data;
        });
    }

    public static function requiredFieldProvider(): array
    {
        return [
            'match_id' => ['match_id'],
            'duration' => ['duration'],
            'radiant_win' => ['radiant_win'],
            'players' => ['players'],
        ];
    }

    public function test_toEntityFailsOnEmptyData()
    {
        $this->expectException(SourceDataException::class);

        $this->mapper->toEntity([]);
    }

    public function test_toEntityResolvesDireJungleLaneFromLaneRole()
    {
        // подробные случаи линий — в LaneNormalizerTest; здесь проверяется связка маппера
        $match = $this->mapFixture(static function (array $data): array {
            foreach ($data['players'] as &$player) {
                if ($player['account_id'] === 249835593) {
                    $player['lane'] = 5;
                    $player['lane_role'] = 4;
                }
            }

            return $data;
        });

        $this->assertSame(Lane::OFFLANE, $this->findPlayer($match, 249835593)->getIdentity()->getLane()->getValue());
    }

    /**
     * @param callable(array): array|null $modify правка сырого ответа OpenDota перед маппингом
     */
    private function mapFixture(?callable $modify = null): MatchDetail
    {
        $data = json_decode(file_get_contents(self::FIXTURE), true);

        if (null !== $modify) {
            $data = $modify($data);
        }

        return $this->mapper->toEntity($this->extractor->extract($data));
    }

    private function findPlayer(MatchDetail $match, int $accountId): MatchPlayerPerformance
    {
        foreach ($match->getPlayers()->getValue() as $player) {
            if ($player->getIdentity()->getPlayerId()->toInt() === $accountId) {
                return $player;
            }
        }

        $this->fail(sprintf('Player %d not found', $accountId));
    }
}
