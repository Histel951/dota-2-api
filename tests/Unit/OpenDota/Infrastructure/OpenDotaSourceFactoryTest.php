<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Tests\Unit\OpenDota\Infrastructure;

use Histel951\Dota2Api\OpenDota\Infrastructure\ApiGatewayInterface;
use Histel951\Dota2Api\OpenDota\Infrastructure\Match\OpenDotaMatchSource;
use Histel951\Dota2Api\OpenDota\Infrastructure\OpenDotaSourceFactory;
use Histel951\Dota2Api\OpenDota\Infrastructure\Team\OpenDotaTeamSource;
use Histel951\Dota2Api\OpenDota\Match\Domain\Player\PlayerRole;
use Histel951\Dota2Api\OpenDota\Match\Port\MatchSourceInterface;
use Histel951\Dota2Api\OpenDota\Team\Port\TeamSourceInterface;
use Histel951\Dota2Api\SharedKernel\MatchId;
use PHPUnit\Framework\TestCase;

class OpenDotaSourceFactoryTest extends TestCase
{
    private const string FIXTURE = __DIR__ . '/../../../Fixtures/opendota/match_8794744726.json';

    public function test_createMatchSourceReturnsOpenDotaMatchSource()
    {
        $source = (new OpenDotaSourceFactory($this->createStub(ApiGatewayInterface::class)))->createMatchSource();

        $this->assertInstanceOf(MatchSourceInterface::class, $source);
        $this->assertInstanceOf(OpenDotaMatchSource::class, $source);
    }

    public function test_createTeamSourceReturnsOpenDotaTeamSource()
    {
        $source = (new OpenDotaSourceFactory($this->createStub(ApiGatewayInterface::class)))->createTeamSource();

        $this->assertInstanceOf(TeamSourceInterface::class, $source);
        $this->assertInstanceOf(OpenDotaTeamSource::class, $source);
    }

    public function test_matchSourceUsesGivenGateway()
    {
        $gateway = $this->createMock(ApiGatewayInterface::class);
        $gateway->expects($this->once())
            ->method('get')
            ->with('matches/8794744726')
            ->willReturn(json_decode(file_get_contents(self::FIXTURE), true));

        $match = (new OpenDotaSourceFactory($gateway))->createMatchSource()->getMatch(new MatchId(8794744726));

        $this->assertSame(8794744726, $match->getId()->getValue());
    }

    public function test_factoryCreatesNewSourceOnEachCall()
    {
        $factory = new OpenDotaSourceFactory($this->createStub(ApiGatewayInterface::class));

        $this->assertNotSame($factory->createMatchSource(), $factory->createMatchSource());
        $this->assertNotSame($factory->createTeamSource(), $factory->createTeamSource());
    }

    public function test_matchSourceDoesNotResolveRoles()
    {
        // фабрика без резолвера: роли выставляет MatchService
        $data = json_decode(file_get_contents(self::FIXTURE), true);
        foreach ($data['players'] as &$player) {
            unset($player['position_est']);
        }
        unset($player);

        $gateway = $this->createStub(ApiGatewayInterface::class);
        $gateway->method('get')->willReturn($data);

        $match = (new OpenDotaSourceFactory($gateway))->createMatchSource()->getMatch(new MatchId(8794744726));

        foreach ($match->getPlayers()->getValue() as $player) {
            $this->assertSame(PlayerRole::UNKNOWN, $player->getRole()->getValue());
        }
    }
}
