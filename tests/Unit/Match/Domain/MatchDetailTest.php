<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Tests\Unit\Match\Domain;

use Histel951\Dota2Api\Match\Domain\Draft\Draft;
use Histel951\Dota2Api\Match\Domain\MatchStartTime;
use Histel951\Dota2Api\Match\Domain\Player\Identity;
use Histel951\Dota2Api\Match\Domain\Player\Lane;
use Histel951\Dota2Api\Match\Domain\Player\MatchPlayerLane;
use Histel951\Dota2Api\Match\Domain\Player\MatchPlayerPerformance;
use Histel951\Dota2Api\Match\Domain\Player\MatchPlayers;
use Histel951\Dota2Api\Match\Domain\Player\PlayerRole;
use Histel951\Dota2Api\Match\Domain\Player\Role;
use Histel951\Dota2Api\Match\Domain\Player\Stats\AncientCreeps;
use Histel951\Dota2Api\Match\Domain\Player\Stats\BuybackCount;
use Histel951\Dota2Api\Match\Domain\Player\Stats\CampsStacked;
use Histel951\Dota2Api\Match\Domain\Player\Stats\CourierKills;
use Histel951\Dota2Api\Match\Domain\Player\Stats\CreepsStats;
use Histel951\Dota2Api\Match\Domain\Player\Stats\DamageStats;
use Histel951\Dota2Api\Match\Domain\Player\Stats\Denies;
use Histel951\Dota2Api\Match\Domain\Player\Stats\GoldSpent;
use Histel951\Dota2Api\Match\Domain\Player\Stats\GPM;
use Histel951\Dota2Api\Match\Domain\Player\Stats\HeroDamage;
use Histel951\Dota2Api\Match\Domain\Player\Stats\HeroHealing;
use Histel951\Dota2Api\Match\Domain\Player\Stats\KDA;
use Histel951\Dota2Api\Match\Domain\Player\Stats\LaneCreeps;
use Histel951\Dota2Api\Match\Domain\Player\Stats\LastHits;
use Histel951\Dota2Api\Match\Domain\Player\Stats\MatchPlayerEconomy;
use Histel951\Dota2Api\Match\Domain\Player\Stats\NetWorth;
use Histel951\Dota2Api\Match\Domain\Player\Stats\NeutralCreeps;
use Histel951\Dota2Api\Match\Domain\Player\Stats\ObjectivesStats;
use Histel951\Dota2Api\Match\Domain\Player\Stats\ObserversDestroyed;
use Histel951\Dota2Api\Match\Domain\Player\Stats\ObserversPlaced;
use Histel951\Dota2Api\Match\Domain\Player\Stats\RoshanKills;
use Histel951\Dota2Api\Match\Domain\Player\Stats\RunePickups;
use Histel951\Dota2Api\Match\Domain\Player\Stats\SentryDestroyed;
use Histel951\Dota2Api\Match\Domain\Player\Stats\SentryPlaced;
use Histel951\Dota2Api\Match\Domain\Player\Stats\SmokeUses;
use Histel951\Dota2Api\Match\Domain\Player\Stats\Stuns;
use Histel951\Dota2Api\Match\Domain\Player\Stats\TeamfightParticipation;
use Histel951\Dota2Api\Match\Domain\Player\Stats\TormentorKills;
use Histel951\Dota2Api\Match\Domain\Player\Stats\TotalGold;
use Histel951\Dota2Api\Match\Domain\Player\Stats\TotalXP;
use Histel951\Dota2Api\Match\Domain\Player\Stats\TowerDamage;
use Histel951\Dota2Api\Match\Domain\Player\Stats\TowerKills;
use Histel951\Dota2Api\Match\Domain\Player\Stats\UtilityStats;
use Histel951\Dota2Api\Match\Domain\Player\Stats\Watchers;
use Histel951\Dota2Api\Match\Domain\Player\Stats\WardingStats;
use Histel951\Dota2Api\Match\Domain\Player\Stats\XPM;
use Histel951\Dota2Api\Match\Domain\Role\RoleResolverInterface;
use Histel951\Dota2Api\Match\Domain\MatchDetail;
use Histel951\Dota2Api\Match\Domain\Side\MatchResult;
use Histel951\Dota2Api\Match\Domain\Side\Side;
use Histel951\Dota2Api\Match\Domain\Side\TeamSide;
use Histel951\Dota2Api\SharedKernel\Duration;
use Histel951\Dota2Api\SharedKernel\Exception\DomainException;
use Histel951\Dota2Api\SharedKernel\HeroId;
use Histel951\Dota2Api\SharedKernel\MatchId;
use Histel951\Dota2Api\SharedKernel\PlayerId;
use Histel951\Dota2Api\SharedKernel\PlayerName;
use Histel951\Dota2Api\SharedKernel\TeamId;
use Histel951\Dota2Api\SharedKernel\TeamName;
use PHPUnit\Framework\TestCase;

class MatchDetailTest extends TestCase
{
    public function test_resultAndPlayersAreDerivedFromSides(): void
    {
        $radiantPlayers = [$this->createPlayer(1, Side::RADIANT), $this->createPlayer(2, Side::RADIANT)];
        $direPlayers = [$this->createPlayer(3, Side::DIRE), $this->createPlayer(4, Side::DIRE)];
        $radiant = $this->createSide(Side::RADIANT, true, $radiantPlayers);
        $dire = $this->createSide(Side::DIRE, false, $direPlayers);

        $detail = $this->createDetail($radiant, $dire);

        $this->assertSame($radiant, $detail->getResult()->getWinner());
        $this->assertSame($dire, $detail->getResult()->getLoser());
        $this->assertSame(
            [...$radiantPlayers, ...$direPlayers],
            $detail->getPlayers()->getValue(),
        );
    }

    public function test_matchResultFromSidesWhenDireWon(): void
    {
        $radiant = $this->createSide(Side::RADIANT, false, []);
        $dire = $this->createSide(Side::DIRE, true, []);

        $result = MatchResult::fromSides($radiant, $dire);

        $this->assertSame($dire, $result->getWinner());
        $this->assertSame($radiant, $result->getLoser());
    }

    public function test_matchResultFromSidesWhenRadiantWon(): void
    {
        $radiant = $this->createSide(Side::RADIANT, true, []);
        $dire = $this->createSide(Side::DIRE, false, []);

        $result = MatchResult::fromSides($radiant, $dire);

        $this->assertSame($radiant, $result->getWinner());
        $this->assertSame($dire, $result->getLoser());
    }

    public function test_withResolvedRolesCallsResolverPerSideAndRebuildsDetail(): void
    {
        $radiant = $this->createSide(Side::RADIANT, false, [$this->createPlayer(1, Side::RADIANT)]);
        $dire = $this->createSide(Side::DIRE, true, [$this->createPlayer(2, Side::DIRE)]);
        $detail = $this->createDetail($radiant, $dire, Duration::fromSeconds(1800));

        $resolver = new class implements RoleResolverInterface {
            /** @var list<bool> */
            public array $calls = [];

            public function resolve(array $players, bool $isRadiant = true): array
            {
                $this->calls[] = $isRadiant;
                $role = new Role($isRadiant ? PlayerRole::CARRY : PlayerRole::SUPPORT);

                return array_map(static fn(MatchPlayerPerformance $p) => $p->withRole($role), $players);
            }
        };

        $resolved = $detail->withResolvedRoles($resolver);

        $this->assertSame([true, false], $resolver->calls);
        $this->assertNotSame($detail, $resolved);
        $this->assertSame(
            PlayerRole::CARRY,
            $resolved->getRadiant()->getPlayers()->getValue()[0]->getRole()->getValue(),
        );
        $this->assertSame(
            PlayerRole::SUPPORT,
            $resolved->getDire()->getPlayers()->getValue()[0]->getRole()->getValue(),
        );
        $this->assertSame(
            [PlayerRole::CARRY, PlayerRole::SUPPORT],
            array_map(
                static fn(MatchPlayerPerformance $p) => $p->getRole()->getValue(),
                $resolved->getPlayers()->getValue(),
            ),
        );
        // Исходный матч не меняется
        $this->assertSame(
            PlayerRole::UNKNOWN,
            $detail->getRadiant()->getPlayers()->getValue()[0]->getRole()->getValue(),
        );

        // Остальное сохраняется
        $this->assertSame($detail->getId(), $resolved->getId());
        $this->assertSame($detail->getDuration(), $resolved->getDuration());
        $this->assertSame($detail->getDraft(), $resolved->getDraft());
        $this->assertSame($detail->getStartTime(), $resolved->getStartTime());
        $this->assertSame($detail->getRegion(), $resolved->getRegion());
        $this->assertSame($radiant->getDraft(), $resolved->getRadiant()->getDraft());
        $this->assertSame($dire->getTeamName(), $resolved->getDire()->getTeamName());
        $this->assertSame($dire->getSide(), $resolved->getDire()->getSide());
        $this->assertFalse($resolved->getRadiant()->isWon());
        $this->assertTrue($resolved->getDire()->isWon());
        $this->assertSame($resolved->getDire(), $resolved->getResult()->getWinner());
    }

    public function test_withResolvedRolesPropagatesResolverException(): void
    {
        $detail = $this->createDetail(
            $this->createSide(Side::RADIANT, true, [$this->createPlayer(1, Side::RADIANT)]),
            $this->createSide(Side::DIRE, false, [$this->createPlayer(2, Side::DIRE)]),
        );

        $resolver = new class implements RoleResolverInterface {
            public function resolve(array $players, bool $isRadiant = true): array
            {
                throw new DomainException('resolver failed');
            }
        };

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('resolver failed');

        $detail->withResolvedRoles($resolver);
    }

    private function createDetail(TeamSide $radiant, TeamSide $dire, ?Duration $duration = null): MatchDetail
    {
        return new MatchDetail(
            id: new MatchId(1),
            duration: $duration ?? Duration::fromSeconds(60),
            radiant: $radiant,
            dire: $dire,
            draft: new Draft(picks: [], bans: []),
            startTime: MatchStartTime::fromTimestamp(1700000000),
            region: null,
        );
    }

    /**
     * @param MatchPlayerPerformance[] $players
     */
    private function createSide(Side $side, bool $won, array $players): TeamSide
    {
        return new TeamSide(
            teamId: new TeamId(null),
            teamName: new TeamName(null),
            won: $won,
            draft: new Draft(picks: [], bans: []),
            players: new MatchPlayers($players),
            side: $side,
        );
    }

    private function createPlayer(int $accountId, Side $side): MatchPlayerPerformance
    {
        return new MatchPlayerPerformance(
            identity: new Identity(
                playerId: new PlayerId($accountId),
                heroId: new HeroId($accountId),
                side: $side,
                lane: new MatchPlayerLane(Lane::SAFE),
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
                GPM: new GPM(400),
            ),
            warding: new WardingStats(
                observersPlaced: new ObserversPlaced(0),
                sentryPlaced: new SentryPlaced(0),
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
