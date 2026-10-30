<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Tests\Unit\Stratz\Match\Domain;

use Histel951\Dota2Api\SharedKernel\Draft\Draft;
use Histel951\Dota2Api\SharedKernel\Duration;
use Histel951\Dota2Api\SharedKernel\GPM;
use Histel951\Dota2Api\SharedKernel\HeroId;
use Histel951\Dota2Api\SharedKernel\KDA;
use Histel951\Dota2Api\SharedKernel\MatchId;
use Histel951\Dota2Api\SharedKernel\MatchStartTime;
use Histel951\Dota2Api\SharedKernel\PlayerId;
use Histel951\Dota2Api\SharedKernel\Region\Region;
use Histel951\Dota2Api\SharedKernel\Side;
use Histel951\Dota2Api\SharedKernel\TeamId;
use Histel951\Dota2Api\SharedKernel\TeamName;
use Histel951\Dota2Api\SharedKernel\XPM;
use Histel951\Dota2Api\Stratz\Match\Domain\Exception\InvalidMatchSideException;
use Histel951\Dota2Api\Stratz\Match\Domain\MatchDetail;
use Histel951\Dota2Api\Stratz\Match\Domain\Player\Identity;
use Histel951\Dota2Api\Stratz\Match\Domain\Player\Lane;
use Histel951\Dota2Api\Stratz\Match\Domain\Player\MatchPlayer;
use Histel951\Dota2Api\Stratz\Match\Domain\Player\Stats\Damage;
use Histel951\Dota2Api\Stratz\Match\Domain\Player\Stats\Denies;
use Histel951\Dota2Api\Stratz\Match\Domain\Player\Stats\Economy;
use Histel951\Dota2Api\Stratz\Match\Domain\Player\Stats\Farm;
use Histel951\Dota2Api\Stratz\Match\Domain\Player\Stats\GoldSpent;
use Histel951\Dota2Api\Stratz\Match\Domain\Player\Stats\HeroDamage;
use Histel951\Dota2Api\Stratz\Match\Domain\Player\Stats\HeroHealing;
use Histel951\Dota2Api\Stratz\Match\Domain\Player\Stats\LastHits;
use Histel951\Dota2Api\Stratz\Match\Domain\Player\Stats\NetWorth;
use Histel951\Dota2Api\Stratz\Match\Domain\Player\Stats\TowerDamage;
use Histel951\Dota2Api\Stratz\Match\Domain\TeamSide;
use PHPUnit\Framework\TestCase;

class MatchDetailTest extends TestCase
{
    public function test_constructorKeepsValues()
    {
        $radiant = $this->createSide(Side::RADIANT, [1, 2]);
        $dire = $this->createSide(Side::DIRE, [3]);
        $draft = new Draft([], []);

        $match = $this->createMatch($radiant, $dire, Side::RADIANT, $draft, true);

        $this->assertSame(9026055036, $match->getId()->getValue());
        $this->assertSame(2835, $match->getDuration()->getSeconds());
        $this->assertSame(1790954533, $match->getStartTime()->getValue()->getTimestamp());
        $this->assertSame(3, $match->getRegion()->getId());
        $this->assertSame($radiant, $match->getRadiant());
        $this->assertSame($dire, $match->getDire());
        $this->assertSame($draft, $match->getDraft());
        $this->assertTrue($match->isParsed());
    }

    public function test_getWinnerReturnsRadiantSide()
    {
        $radiant = $this->createSide(Side::RADIANT, [1]);
        $dire = $this->createSide(Side::DIRE, [2]);

        $this->assertSame($radiant, $this->createMatch($radiant, $dire, Side::RADIANT)->getWinner());
    }

    public function test_getWinnerReturnsDireSide()
    {
        $radiant = $this->createSide(Side::RADIANT, [1]);
        $dire = $this->createSide(Side::DIRE, [2]);

        $this->assertSame($dire, $this->createMatch($radiant, $dire, Side::DIRE)->getWinner());
    }

    public function test_getPlayersReturnsRadiantThenDire()
    {
        $match = $this->createMatch(
            $this->createSide(Side::RADIANT, [10, 20]),
            $this->createSide(Side::DIRE, [30, 40]),
            Side::DIRE,
        );

        $ids = array_map(
            static fn(MatchPlayer $p): int => $p->getIdentity()->getPlayerId()->toInt(),
            $match->getPlayers(),
        );

        $this->assertSame([10, 20, 30, 40], $ids);
    }

    public function test_unparsedMatchHasNoDraft()
    {
        $match = $this->createMatch(
            $this->createSide(Side::RADIANT, []),
            $this->createSide(Side::DIRE, []),
            Side::RADIANT,
            null,
            false,
        );

        $this->assertNull($match->getDraft());
        $this->assertFalse($match->isParsed());
        $this->assertSame([], $match->getPlayers());
    }

    public function test_direSideInRadiantSlotThrows()
    {
        $this->expectException(InvalidMatchSideException::class);

        $this->createMatch($this->createSide(Side::DIRE, []), $this->createSide(Side::DIRE, []), Side::DIRE);
    }

    public function test_radiantSideInDireSlotThrows()
    {
        $this->expectException(InvalidMatchSideException::class);

        $this->createMatch($this->createSide(Side::RADIANT, []), $this->createSide(Side::RADIANT, []), Side::RADIANT);
    }

    private function createMatch(
        TeamSide $radiant,
        TeamSide $dire,
        Side $winner,
        ?Draft $draft = null,
        bool $isParsed = false,
    ): MatchDetail
    {
        return new MatchDetail(
            id: new MatchId(9026055036),
            duration: Duration::fromSeconds(2835),
            startTime: MatchStartTime::fromTimestamp(1790954533),
            region: Region::fromId(3),
            radiant: $radiant,
            dire: $dire,
            winner: $winner,
            draft: $draft,
            isParsed: $isParsed,
        );
    }

    /**
     * @param list<int> $accountIds
     */
    private function createSide(Side $side, array $accountIds): TeamSide
    {
        return new TeamSide(
            $side,
            new TeamId(),
            new TeamName(null),
            array_map(fn(int $id): MatchPlayer => $this->createPlayer($id, $side), $accountIds),
        );
    }

    private function createPlayer(int $accountId, Side $side): MatchPlayer
    {
        return new MatchPlayer(
            identity: new Identity(
                playerId: new PlayerId($accountId),
                heroId: new HeroId(1),
                side: $side,
                lane: Lane::UNKNOWN,
                position: null,
                proName: null,
                personaName: null,
            ),
            kda: new KDA(1, 1, 1),
            economy: new Economy(new GPM(1), new XPM(1), new NetWorth(1), new GoldSpent(1)),
            damage: new Damage(new HeroDamage(1), new TowerDamage(1), new HeroHealing(1)),
            farm: new Farm(new LastHits(1), new Denies(1)),
            parsedStats: null,
        );
    }
}
