<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Tests\Unit\Stratz\Match\Domain;

use Histel951\Dota2Api\SharedKernel\GPM;
use Histel951\Dota2Api\SharedKernel\HeroId;
use Histel951\Dota2Api\SharedKernel\KDA;
use Histel951\Dota2Api\SharedKernel\PlayerId;
use Histel951\Dota2Api\SharedKernel\Side;
use Histel951\Dota2Api\SharedKernel\TeamId;
use Histel951\Dota2Api\SharedKernel\TeamName;
use Histel951\Dota2Api\SharedKernel\XPM;
use Histel951\Dota2Api\Stratz\Match\Domain\Exception\InvalidMatchSideException;
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

class TeamSideTest extends TestCase
{
    public function test_constructorKeepsValues()
    {
        $players = [$this->createPlayer(1, Side::RADIANT), $this->createPlayer(2, Side::RADIANT)];

        $side = new TeamSide(Side::RADIANT, new TeamId(15), new TeamName('LGD Gaming'), $players);

        $this->assertSame(Side::RADIANT, $side->getSide());
        $this->assertSame('15', $side->getTeamId()->getValue());
        $this->assertSame('LGD Gaming', $side->getTeamName()->getValue());
        $this->assertSame($players, $side->getPlayers());
        $this->assertTrue($side->isRadiant());
    }

    public function test_direSideIsNotRadiant()
    {
        $side = new TeamSide(Side::DIRE, new TeamId(), new TeamName(null), [$this->createPlayer(1, Side::DIRE)]);

        $this->assertFalse($side->isRadiant());
        $this->assertNull($side->getTeamId()->getValue());
        $this->assertNull($side->getTeamName()->getValue());
    }

    public function test_emptyPlayersAreAllowed()
    {
        $side = new TeamSide(Side::RADIANT, new TeamId(), new TeamName(null), []);

        $this->assertSame([], $side->getPlayers());
    }

    public function test_direPlayerInRadiantSideThrows()
    {
        $this->expectException(InvalidMatchSideException::class);

        new TeamSide(
            Side::RADIANT,
            new TeamId(),
            new TeamName(null),
            [$this->createPlayer(1, Side::RADIANT), $this->createPlayer(2, Side::DIRE)],
        );
    }

    public function test_radiantPlayerInDireSideThrows()
    {
        $this->expectException(InvalidMatchSideException::class);

        new TeamSide(Side::DIRE, new TeamId(), new TeamName(null), [$this->createPlayer(1, Side::RADIANT)]);
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
