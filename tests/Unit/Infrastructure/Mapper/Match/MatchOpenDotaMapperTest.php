<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Tests\Unit\Infrastructure\Mapper\Match;

use Histel951\Dota2Api\Domain\Common\Enums\PlayerRole;
use Histel951\Dota2Api\Domain\Common\Exceptions\DomainException;
use Histel951\Dota2Api\Domain\Entities\Match\Enums\Lane;
use Histel951\Dota2Api\Domain\Entities\Match\Enums\Side;
use Histel951\Dota2Api\Domain\Entities\Match\MatchDetail;
use Histel951\Dota2Api\Domain\Entities\Match\Services\ProSceneRoleResolver;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\MatchPlayerPerformance;
use Histel951\Dota2Api\Infrastructure\Exceptions\MappingException;
use Histel951\Dota2Api\Infrastructure\Mapper\Match\MatchOpenDotaMapper;
use Histel951\Dota2Api\Infrastructure\Provider\Extractors\MatchExtractor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TypeError;

class MatchOpenDotaMapperTest extends TestCase
{
    private const string FIXTURE = __DIR__ . '/../../../../Fixtures/opendota/match_8794744726.json';

    private MatchOpenDotaMapper $mapper;
    private MatchExtractor $extractor;

    public function setUp(): void
    {
        $this->mapper = new MatchOpenDotaMapper(new ProSceneRoleResolver());
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
        $player = $this->findPlayer($this->mapFixture(), $accountId);

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

    public function test_toEntityWrapsDomainErrorIntoMappingException()
    {
        try {
            $this->mapFixture(static function (array $data): array {
                foreach ($data['players'] as &$player) {
                    $player['lane'] = 1;
                }

                return $data;
            });
            $this->fail('MappingException expected');
        } catch (MappingException $e) {
            $this->assertInstanceOf(DomainException::class, $e->getPrevious());
        }
    }

    public function test_toEntityWrapsUnexpectedFieldTypeIntoMappingException()
    {
        try {
            $this->mapFixture(static function (array $data): array {
                $data['players'][0]['account_id'] = null;

                return $data;
            });
            $this->fail('MappingException expected');
        } catch (MappingException $e) {
            $this->assertInstanceOf(TypeError::class, $e->getPrevious());
        }
    }

    #[DataProvider('jungleLaneProvider')]
    public function test_toEntityResolvesJungleLaneFromLaneRole(int $accountId, int $laneRole, Lane $expected)
    {
        $match = $this->mapFixture(static function (array $data) use ($accountId, $laneRole): array {
            foreach ($data['players'] as &$player) {
                if ($player['account_id'] === $accountId) {
                    $player['lane'] = $player['isRadiant'] ? 4 : 5;
                    $player['lane_role'] = $laneRole;
                }
            }

            return $data;
        });

        $player = $this->findPlayer($match, $accountId);

        $this->assertSame($expected, $player->getIdentity()->getLane()->getValue());
    }

    public static function jungleLaneProvider(): array
    {
        // линия возвращается со стороны radiant: safe lane dire — это Lane::OFFLANE
        return [
            'radiant safe' => [898754153, 1, Lane::SAFE],
            'radiant offlane' => [98887913, 3, Lane::OFFLANE],
            'radiant jungle' => [94296097, 4, Lane::SAFE],
            'dire safe' => [170896543, 1, Lane::OFFLANE],
            'dire middle' => [315272623, 2, Lane::MIDDLE],
            'dire offlane' => [145957968, 3, Lane::SAFE],
            'dire jungle' => [249835593, 4, Lane::OFFLANE],
        ];
    }

    /**
     * @param callable(array): array|null $modify правка сырого ответа OpenDota перед маппингом
     */
    private function mapFixture(?callable $modify = null): MatchDetail
    {
        $json = file_get_contents(self::FIXTURE);

        if (null !== $modify) {
            $json = json_encode($modify(json_decode($json, true)));
        }

        return $this->mapper->toEntity($this->extractor->extract($json));
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
