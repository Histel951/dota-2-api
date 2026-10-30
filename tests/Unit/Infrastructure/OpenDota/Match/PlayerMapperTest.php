<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Tests\Unit\Infrastructure\OpenDota\Match;

use Histel951\Dota2Api\Infrastructure\OpenDota\Match\MatchExtractor;
use Histel951\Dota2Api\Infrastructure\OpenDota\Match\PlayerMapper;
use Histel951\Dota2Api\Match\Domain\Exception\InvalidKDAException;
use Histel951\Dota2Api\Match\Domain\Player\Lane;
use Histel951\Dota2Api\Match\Domain\Player\MatchPlayerPerformance;
use Histel951\Dota2Api\Match\Domain\Player\PlayerRole;
use Histel951\Dota2Api\Match\Domain\Side\Side;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TypeError;

class PlayerMapperTest extends TestCase
{
    private const string FIXTURE = __DIR__ . '/../../../../Fixtures/opendota/match_8794744726.json';

    private const int ZZQ = 249835593; // dire, pos5, lane 3 / lane_role 1
    private const int AME = 898754153; // radiant, pos1
    private const int TIGER = 98887913; // radiant, name = null
    private const int FY = 101695162; // radiant, player_slot 3, tormentor 1
    private const int PLANET = 150961567; // dire, player_slot 131, tormentor 1

    public function test_mapMapsDirePlayer()
    {
        $player = $this->map(self::ZZQ);

        $this->assertSame(Side::DIRE, $player->getIdentity()->getSide());
        $this->assertTrue($player->getIdentity()->isDire());
        $this->assertSame(249835593, $player->getIdentity()->getPlayerId()->toInt());
        $this->assertSame(112, $player->getIdentity()->getHeroId()->getValue());
        $this->assertSame('zzq', $player->getIdentity()->getPlayerProName()->getValue());
        $this->assertSame('Gao[o]ne-', $player->getIdentity()->getPlayerName()->getValue());
        $this->assertSame(Lane::OFFLANE, $player->getIdentity()->getLane()->getValue());
        $this->assertSame(PlayerRole::HARD_SUPPORT, $player->getRole()->getValue());
        $this->assertSame([3, 8, 21], [
            $player->getKda()->getKills(),
            $player->getKda()->getDeaths(),
            $player->getKda()->getAssists(),
        ]);
        $this->assertSame(369, $player->getEconomy()->getGPM()->getValue());
        $this->assertSame(455, $player->getEconomy()->getXPM()->getValue());
        $this->assertSame(37, $player->getCreeps()->getLastHits()->getValue());
        $this->assertSame(27, $player->getCreeps()->getLaneCreeps()->getValue());
        $this->assertSame(15505, $player->getDamage()->getHeroDamage()->getValue());
        $this->assertSame(21, $player->getWarding()->getSentryPlaced()->getValue());
        $this->assertSame(3, $player->getWarding()->getObserverTakeovers()->getValue());
        $this->assertSame(4, $player->getUtility()->getSmokeUses()->getValue());
    }

    public function test_mapMapsRadiantPlayer()
    {
        $player = $this->map(self::AME);

        $this->assertSame(Side::RADIANT, $player->getIdentity()->getSide());
        $this->assertSame(Lane::SAFE, $player->getIdentity()->getLane()->getValue());
        $this->assertSame(PlayerRole::CARRY, $player->getRole()->getValue());
    }

    public function test_mapFallsBackToPersonaNameWhenProNameIsMissing()
    {
        $player = $this->map(self::TIGER);

        $this->assertSame('TIGER', $player->getIdentity()->getPlayerProName()->getValue());
        $this->assertSame('TIGER', $player->getIdentity()->getPlayerName()->getValue());
    }

    public function test_mapDefaultsAbilityAndItemUsesToZeroWhenAbsent()
    {
        $player = $this->map(self::AME, static function (array $raw): array {
            $raw['ability_uses'] = null;
            $raw['item_uses'] = null;

            return $raw;
        });

        $this->assertSame(0, $player->getWarding()->getObserverTakeovers()->getValue());
        $this->assertSame(0, $player->getUtility()->getSmokeUses()->getValue());
    }

    public function test_mapCountsTormentorKillsBySlot()
    {
        $objectives = $this->fixture()['objectives'];

        $this->assertSame(1, $this->map(self::PLANET, null, $objectives)->getObjectives()->getTormentorKills()->getValue());
        $this->assertSame(1, $this->map(self::FY, null, $objectives)->getObjectives()->getTormentorKills()->getValue());
        $this->assertSame(0, $this->map(self::AME, null, $objectives)->getObjectives()->getTormentorKills()->getValue());
    }

    public function test_mapReturnsZeroTormentorKillsForUnparsedMatch()
    {
        $player = $this->map(self::PLANET, null, []);

        $this->assertSame(0, $player->getObjectives()->getTormentorKills()->getValue());
    }

    public function test_mapCountsTwoTormentorKillsOfSamePlayer()
    {
        $objectives = [
            ['type' => 'CHAT_MESSAGE_MINIBOSS_KILL', 'player_slot' => 131],
            ['type' => 'CHAT_MESSAGE_MINIBOSS_KILL', 'player_slot' => 131],
            ['type' => 'CHAT_MESSAGE_ROSHAN_KILL', 'player_slot' => 131],
            ['type' => 'CHAT_MESSAGE_MINIBOSS_KILL', 'player_slot' => 3],
        ];

        $player = $this->map(self::PLANET, null, $objectives);

        $this->assertSame(2, $player->getObjectives()->getTormentorKills()->getValue());
    }

    #[DataProvider('positionProvider')]
    public function test_mapTakesInitialRoleFromPositionEst(mixed $position, PlayerRole $expected)
    {
        $player = $this->map(self::AME, static function (array $raw) use ($position): array {
            $raw['position_est'] = $position;

            return $raw;
        });

        $this->assertSame($expected, $player->getRole()->getValue());
    }

    public static function positionProvider(): array
    {
        return [
            '1' => [1, PlayerRole::CARRY],
            '2' => [2, PlayerRole::MIDDLE],
            '3' => [3, PlayerRole::OFFLANE],
            '4' => [4, PlayerRole::SUPPORT],
            '5' => [5, PlayerRole::HARD_SUPPORT],
            'null' => [null, PlayerRole::UNKNOWN],
            'zero' => [0, PlayerRole::UNKNOWN],
            'six' => [6, PlayerRole::UNKNOWN],
            'negative' => [-1, PlayerRole::UNKNOWN],
            'numeric string' => ['3', PlayerRole::UNKNOWN],
            'float' => [3.5, PlayerRole::UNKNOWN],
            'bool' => [true, PlayerRole::UNKNOWN],
        ];
    }

    public function test_mapUsesLaneNormalizerForJungleLane()
    {
        $player = $this->map(self::ZZQ, static function (array $raw): array {
            $raw['lane'] = 5;
            $raw['lane_role'] = 4;

            return $raw;
        });

        $this->assertSame(Lane::OFFLANE, $player->getIdentity()->getLane()->getValue());
    }

    public function test_mapHandlesUnparsedPlayerWithoutLane()
    {
        $player = $this->map(self::AME, static function (array $raw): array {
            unset($raw['lane'], $raw['lane_role'], $raw['position_est']);

            return $raw;
        });

        $this->assertSame(Lane::SAFE, $player->getIdentity()->getLane()->getValue());
        $this->assertSame(PlayerRole::UNKNOWN, $player->getRole()->getValue());
    }

    public function test_mapThrowsOnNegativeKda()
    {
        $this->expectException(InvalidKDAException::class);

        $this->map(self::AME, static function (array $raw): array {
            $raw['kills'] = -1;

            return $raw;
        });
    }

    public function test_mapThrowsTypeErrorOnMissingRequiredPlayerField()
    {
        // сырую ошибку оборачивает только MatchMapper::toEntity()
        $this->expectException(TypeError::class);

        $this->map(self::AME, static function (array $raw): array {
            $raw['account_id'] = null;

            return $raw;
        });
    }

    public function test_mapThrowsTypeErrorOnMissingPersonaName()
    {
        $this->expectException(TypeError::class);

        $this->map(self::AME, static function (array $raw): array {
            $raw['personaname'] = null;

            return $raw;
        });
    }

    /**
     * @param callable(array): array|null $modify правка извлечённого игрока
     * @param array|null $objectives objectives матча; по умолчанию из фикстуры
     */
    private function map(int $accountId, ?callable $modify = null, ?array $objectives = null): MatchPlayerPerformance
    {
        $data = $this->fixture();
        $extracted = (new MatchExtractor())->extract($data);

        foreach ($extracted['players'] as $player) {
            if ($player['account_id'] === $accountId) {
                if (null !== $modify) {
                    $player = $modify($player);
                }

                return (new PlayerMapper())->map($player, $objectives ?? $data['objectives']);
            }
        }

        $this->fail(sprintf('Player %d not found', $accountId));
    }

    private function fixture(): array
    {
        return json_decode(file_get_contents(self::FIXTURE), true);
    }
}
