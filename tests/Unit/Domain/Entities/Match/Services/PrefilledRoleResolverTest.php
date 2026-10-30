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
use Histel951\Dota2Api\Domain\Entities\Match\Services\PrefilledRoleResolver;
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
use Histel951\Dota2Api\Domain\Services\RoleResolverInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PrefilledRoleResolverTest extends TestCase
{
    private RoleResolverInterface&MockObject $inner;
    private PrefilledRoleResolver $resolver;

    public function setUp(): void
    {
        $this->inner = $this->createMock(RoleResolverInterface::class);
        $this->resolver = new PrefilledRoleResolver($this->inner);
    }

    #[DataProvider('sideProvider')]
    public function test_resolveKeepsCompleteRoleSetWithoutCallingInner(bool $isRadiant)
    {
        $players = $this->createTeam([
            PlayerRole::HARD_SUPPORT,
            PlayerRole::CARRY,
            PlayerRole::SUPPORT,
            PlayerRole::MIDDLE,
            PlayerRole::OFFLANE,
        ]);

        $this->inner->expects($this->never())->method('resolve');

        $result = $this->resolver->resolve($players, $isRadiant);

        $this->assertSame($players, $result);
    }

    public static function sideProvider(): array
    {
        return ['radiant' => [true], 'dire' => [false]];
    }

    /**
     * @param PlayerRole[] $roles
     */
    #[DataProvider('incompleteRolesProvider')]
    public function test_resolveDelegatesWholeSideToInnerWhenRoleSetIsIncomplete(array $roles, bool $isRadiant)
    {
        $players = $this->createTeam($roles);
        $resolved = $this->createTeam([
            PlayerRole::CARRY,
            PlayerRole::MIDDLE,
            PlayerRole::OFFLANE,
            PlayerRole::SUPPORT,
            PlayerRole::HARD_SUPPORT,
        ]);

        $this->inner
            ->expects($this->once())
            ->method('resolve')
            ->with($players, $isRadiant)
            ->willReturn($resolved);

        $this->assertSame($resolved, $this->resolver->resolve($players, $isRadiant));
    }

    public static function incompleteRolesProvider(): array
    {
        return [
            'one unknown, radiant' => [
                [PlayerRole::CARRY, PlayerRole::MIDDLE, PlayerRole::OFFLANE, PlayerRole::SUPPORT, PlayerRole::UNKNOWN],
                true,
            ],
            'one unknown, dire' => [
                [PlayerRole::UNKNOWN, PlayerRole::MIDDLE, PlayerRole::OFFLANE, PlayerRole::SUPPORT, PlayerRole::HARD_SUPPORT],
                false,
            ],
            'all unknown' => [array_fill(0, 5, PlayerRole::UNKNOWN), true],
            'duplicated role' => [
                [PlayerRole::CARRY, PlayerRole::CARRY, PlayerRole::OFFLANE, PlayerRole::SUPPORT, PlayerRole::HARD_SUPPORT],
                true,
            ],
            'duplicated role with unknown' => [
                [PlayerRole::CARRY, PlayerRole::CARRY, PlayerRole::OFFLANE, PlayerRole::SUPPORT, PlayerRole::UNKNOWN],
                false,
            ],
        ];
    }

    #[DataProvider('wrongTeamSizeProvider')]
    public function test_resolveDelegatesToInnerWhenTeamSizeIsNotFive(int $size)
    {
        $roles = array_slice([
            PlayerRole::CARRY,
            PlayerRole::MIDDLE,
            PlayerRole::OFFLANE,
            PlayerRole::SUPPORT,
            PlayerRole::HARD_SUPPORT,
            PlayerRole::CARRY,
        ], 0, $size);
        $players = $this->createTeam($roles);

        $this->inner
            ->expects($this->once())
            ->method('resolve')
            ->with($players, true)
            ->willReturn($players);

        $this->assertSame($players, $this->resolver->resolve($players));
    }

    public static function wrongTeamSizeProvider(): array
    {
        return ['four players' => [4], 'six players' => [6], 'no players' => [0]];
    }

    public function test_resolvePropagatesInnerDomainException()
    {
        $players = $this->createTeam(array_fill(0, 4, PlayerRole::UNKNOWN));

        $this->inner
            ->expects($this->once())
            ->method('resolve')
            ->willThrowException(new DomainException('Expected exactly 5 players, got 4.'));

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Expected exactly 5 players, got 4.');

        $this->resolver->resolve($players);
    }

    /**
     * @param PlayerRole[] $roles
     * @return MatchPlayerPerformance[]
     */
    private function createTeam(array $roles): array
    {
        $players = [];
        foreach (array_values($roles) as $index => $role) {
            $players[] = $this->createPlayer($index + 1, Lane::SAFE, role: $role);
        }

        return $players;
    }

    private function createPlayer(
        int $accountId,
        Lane $lane,
        PlayerRole $role = PlayerRole::UNKNOWN,
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
            role: new Role($role),
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
