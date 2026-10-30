<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Tests\Unit\Domain\Entities\Match\Services;

use Histel951\Dota2Api\Domain\Common\Enums\PlayerRole;
use Histel951\Dota2Api\SharedKernel\Exception\DomainException;
use Histel951\Dota2Api\Domain\Common\ValueObjects\GoldSpent;
use Histel951\Dota2Api\Domain\Common\ValueObjects\GPM;
use Histel951\Dota2Api\SharedKernel\HeroId;
use Histel951\Dota2Api\Domain\Common\ValueObjects\KDA;
use Histel951\Dota2Api\Domain\Common\ValueObjects\NetWorth;
use Histel951\Dota2Api\SharedKernel\PlayerId;
use Histel951\Dota2Api\SharedKernel\PlayerName;
use Histel951\Dota2Api\Domain\Common\ValueObjects\Role;
use Histel951\Dota2Api\Domain\Common\ValueObjects\TotalGold;
use Histel951\Dota2Api\Domain\Common\ValueObjects\TotalXP;
use Histel951\Dota2Api\Domain\Common\ValueObjects\XPM;
use Histel951\Dota2Api\Domain\Entities\Match\Enums\Lane;
use Histel951\Dota2Api\Domain\Entities\Match\Enums\Side;
use Histel951\Dota2Api\Domain\Entities\Match\Services\ProSceneRoleResolver;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\AncientCreeps;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\BuybackCount;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\CampsStacked;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\CourierKills;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\CreepsStats;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\DamageStats;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\Denies;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\HeroDamage;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\HeroHealing;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\Identity;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\LaneCreeps;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\LastHits;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\MatchPlayerEconomy;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\MatchPlayerLane;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\MatchPlayerPerformance;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\NeutralCreeps;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\ObjectivesStats;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\ObserversDestroyed;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\ObserversPlaced;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\RoshanKills;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\RunePickups;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\SentryDestroyed;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\SentryPlaced;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\SmokeUses;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\Stuns;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\TeamfightParticipation;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\TormentorKills;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\TowerDamage;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\TowerKills;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\UtilityStats;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\WardingStats;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\Watchers;
use PHPUnit\Framework\TestCase;

class ProSceneRoleResolverTest extends TestCase
{
    private ProSceneRoleResolver $resolver;

    public function setUp(): void
    {
        $this->resolver = new ProSceneRoleResolver();
    }

    public function test_resolveStandardRadiantLanes()
    {
        $players = [
            $this->createPlayer(1, Lane::SAFE, gpm: 700),
            $this->createPlayer(2, Lane::SAFE, gpm: 300),
            $this->createPlayer(3, Lane::MIDDLE, gpm: 600),
            $this->createPlayer(4, Lane::OFFLANE, gpm: 500),
            $this->createPlayer(5, Lane::OFFLANE, gpm: 280),
        ];

        $this->assertSame([
            3 => PlayerRole::MIDDLE,
            1 => PlayerRole::CARRY,
            2 => PlayerRole::HARD_SUPPORT,
            4 => PlayerRole::OFFLANE,
            5 => PlayerRole::SUPPORT,
        ], $this->resolveRoles($players));
    }

    public function test_resolveStandardDireLanesAreMirrored()
    {
        // для dire линия от api приходит со стороны radiant: верхняя (OFFLANE) — это safe lane dire
        $players = [
            $this->createPlayer(1, Lane::OFFLANE, gpm: 700, side: Side::DIRE),
            $this->createPlayer(2, Lane::OFFLANE, gpm: 300, side: Side::DIRE),
            $this->createPlayer(3, Lane::MIDDLE, gpm: 600, side: Side::DIRE),
            $this->createPlayer(4, Lane::SAFE, gpm: 500, side: Side::DIRE),
            $this->createPlayer(5, Lane::SAFE, gpm: 280, side: Side::DIRE),
        ];

        $this->assertSame([
            3 => PlayerRole::MIDDLE,
            1 => PlayerRole::CARRY,
            2 => PlayerRole::HARD_SUPPORT,
            4 => PlayerRole::OFFLANE,
            5 => PlayerRole::SUPPORT,
        ], $this->resolveRoles($players, false));
    }

    public function test_resolveTwoMiddlePlayersWithSingleSafeLaner()
    {
        $players = [
            $this->createPlayer(1, Lane::SAFE, gpm: 700),
            $this->createPlayer(2, Lane::MIDDLE, gpm: 300),
            $this->createPlayer(3, Lane::MIDDLE, gpm: 600),
            $this->createPlayer(4, Lane::OFFLANE, gpm: 500),
            $this->createPlayer(5, Lane::OFFLANE, gpm: 280),
        ];

        $this->assertSame([
            3 => PlayerRole::MIDDLE,
            2 => PlayerRole::HARD_SUPPORT,
            1 => PlayerRole::CARRY,
            4 => PlayerRole::OFFLANE,
            5 => PlayerRole::SUPPORT,
        ], $this->resolveRoles($players));
    }

    public function test_resolveTwoMiddlePlayersWithSingleOfflaner()
    {
        $players = [
            $this->createPlayer(1, Lane::SAFE, gpm: 700),
            $this->createPlayer(2, Lane::SAFE, gpm: 300),
            $this->createPlayer(3, Lane::MIDDLE, gpm: 600),
            $this->createPlayer(4, Lane::OFFLANE, gpm: 500),
            $this->createPlayer(5, Lane::MIDDLE, gpm: 280),
        ];

        $this->assertSame([
            3 => PlayerRole::MIDDLE,
            5 => PlayerRole::SUPPORT,
            1 => PlayerRole::CARRY,
            2 => PlayerRole::HARD_SUPPORT,
            4 => PlayerRole::OFFLANE,
        ], $this->resolveRoles($players));
    }

    public function test_resolveThreeMiddlePlayersAssignsSupportsBySentries()
    {
        $players = [
            $this->createPlayer(1, Lane::SAFE, gpm: 700),
            $this->createPlayer(2, Lane::MIDDLE, gpm: 300, sentries: 5),
            $this->createPlayer(3, Lane::MIDDLE, gpm: 600),
            $this->createPlayer(4, Lane::OFFLANE, gpm: 500),
            $this->createPlayer(5, Lane::MIDDLE, gpm: 280, sentries: 20),
        ];

        $this->assertSame([
            3 => PlayerRole::MIDDLE,
            5 => PlayerRole::HARD_SUPPORT,
            2 => PlayerRole::SUPPORT,
            1 => PlayerRole::CARRY,
            4 => PlayerRole::OFFLANE,
        ], $this->resolveRoles($players));
    }

    public function test_resolveThreeSafeLanePlayersAssignsSupportsBySentries()
    {
        $players = [
            $this->createPlayer(1, Lane::SAFE, gpm: 700),
            $this->createPlayer(2, Lane::SAFE, gpm: 300, sentries: 20),
            $this->createPlayer(3, Lane::MIDDLE, gpm: 600),
            $this->createPlayer(4, Lane::OFFLANE, gpm: 500),
            $this->createPlayer(5, Lane::SAFE, gpm: 280, sentries: 5),
        ];

        $this->assertSame([
            3 => PlayerRole::MIDDLE,
            1 => PlayerRole::CARRY,
            2 => PlayerRole::HARD_SUPPORT,
            5 => PlayerRole::SUPPORT,
            4 => PlayerRole::OFFLANE,
        ], $this->resolveRoles($players));
    }

    public function test_resolveThreeMiddlePlayersWithUnbalancedLanesFails()
    {
        $players = [
            $this->createPlayer(1, Lane::SAFE),
            $this->createPlayer(2, Lane::SAFE),
            $this->createPlayer(3, Lane::MIDDLE),
            $this->createPlayer(4, Lane::MIDDLE),
            $this->createPlayer(5, Lane::MIDDLE),
        ];

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('three players on middle lane');

        $this->resolver->resolve($players);
    }

    public function test_resolveWithoutMiddlePlayerFails()
    {
        $players = [
            $this->createPlayer(1, Lane::SAFE),
            $this->createPlayer(2, Lane::SAFE),
            $this->createPlayer(3, Lane::SAFE),
            $this->createPlayer(4, Lane::OFFLANE),
            $this->createPlayer(5, Lane::OFFLANE),
        ];

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Unexpected number of players on middle lane: 0.');

        $this->resolver->resolve($players);
    }

    public function test_resolveWithWrongTeamSizeFails()
    {
        $players = [
            $this->createPlayer(1, Lane::SAFE),
            $this->createPlayer(2, Lane::MIDDLE),
            $this->createPlayer(3, Lane::OFFLANE),
            $this->createPlayer(4, Lane::OFFLANE),
        ];

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Expected exactly 5 players, got 4.');

        $this->resolver->resolve($players);
    }

    /**
     * @param MatchPlayerPerformance[] $players
     * @return array<int, PlayerRole> account id => роль, в порядке, который вернул резолвер
     */
    private function resolveRoles(array $players, bool $isRadiant = true): array
    {
        $roles = [];
        foreach ($this->resolver->resolve($players, $isRadiant) as $player) {
            $roles[$player->getIdentity()->getPlayerId()->toInt()] = $player->getRole()->getValue();
        }

        return $roles;
    }

    private function createPlayer(
        int $accountId,
        Lane $lane,
        int $gpm = 400,
        int $sentries = 0,
        Side $side = Side::RADIANT,
    ): MatchPlayerPerformance
    {
        return new MatchPlayerPerformance(
            identity: new Identity(
                playerId: new PlayerId($accountId),
                heroId: new HeroId($accountId),
                side: $side,
                lane: new MatchPlayerLane($lane),
                playerProName: new PlayerName('player' . $accountId),
                playerPersonaName: new PlayerName('player' . $accountId),
            ),
            kda: new KDA(),
            role: new Role(PlayerRole::UNKNOWN),
            objectives: new ObjectivesStats(
                roshanKills: new RoshanKills(0),
                towerKills: new TowerKills(0),
                runePickups: new RunePickups(0),
                tormentorKills: new TormentorKills(0),
            ),
            creeps: new CreepsStats(
                lastHits: new LastHits(0),
                laneCreeps: new LaneCreeps(0),
                denies: new Denies(0),
                neutralCreeps: new NeutralCreeps(0),
                ancientCreeps: new AncientCreeps(0),
                campsStacked: new CampsStacked(0),
            ),
            economy: new MatchPlayerEconomy(
                netWorth: new NetWorth(0),
                totalGold: new TotalGold(0),
                totalXP: new TotalXP(0),
                goldSpent: new GoldSpent(0),
                XPM: new XPM(0),
                GPM: new GPM($gpm),
            ),
            warding: new WardingStats(
                observersPlaced: new ObserversPlaced(0),
                sentryPlaced: new SentryPlaced($sentries),
                observersDestroyed: new ObserversDestroyed(0),
                sentryDestroyed: new SentryDestroyed(0),
                observerTakeovers: new Watchers(0),
            ),
            damage: new DamageStats(
                heroDamage: new HeroDamage(0),
                towerDamage: new TowerDamage(0),
                heroHealing: new HeroHealing(0),
            ),
            utility: new UtilityStats(
                buybackCount: new BuybackCount(0),
                firstBloodClaimed: false,
                courierKills: new CourierKills(0),
                stuns: new Stuns(0.0),
                teamfightParticipation: new TeamfightParticipation(0.0),
                smokeUses: new SmokeUses(0),
            ),
        );
    }
}
