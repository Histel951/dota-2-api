<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Tests\Unit\Infrastructure\Provider;

use DateTimeImmutable;
use Histel951\Dota2Api\Domain\Common\ValueObjects\TeamId;
use Histel951\Dota2Api\Domain\Common\ValueObjects\TeamName;
use Histel951\Dota2Api\Domain\Entities\Team\Team;
use Histel951\Dota2Api\Domain\Entities\Team\ValueObjects\TeamStats;
use Histel951\Dota2Api\Domain\Entities\Team\ValueObjects\TeamTag;
use Histel951\Dota2Api\Infrastructure\Exceptions\ApiGatewayException;
use Histel951\Dota2Api\Infrastructure\Exceptions\MappingException;
use Histel951\Dota2Api\Infrastructure\Http\OpenDotaApiGateway;
use Histel951\Dota2Api\Infrastructure\Mapper\Team\TeamHeroOpenDotaMapper;
use Histel951\Dota2Api\Infrastructure\Mapper\Team\TeamMatchOpenDotaMapper;
use Histel951\Dota2Api\Infrastructure\Mapper\Team\TeamOpenDotaMapper;
use Histel951\Dota2Api\Infrastructure\Mapper\Team\TeamPlayerOpenDotaMapper;
use Histel951\Dota2Api\Infrastructure\Provider\OpenDotaTeamProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Loader\Configurator\Traits\PropertyTrait;

class OpenDotaTeamProviderTest extends TestCase
{
    use PropertyTrait;

    private $gateway;
    private $teamMapper;
    private $matchesMapper;
    private $playerMapper;
    private $heroMapper;
    private OpenDotaTeamProvider $provider;

    public function setUp(): void
    {
        $this->gateway = $this->createMock(OpenDotaApiGateway::class);
        $this->teamMapper = $this->createMock(TeamOpenDotaMapper::class);
        $this->matchesMapper = $this->createMock(TeamMatchOpenDotaMapper::class);
        $this->playerMapper = $this->createMock(TeamPlayerOpenDotaMapper::class);
        $this->heroMapper = $this->createMock(TeamHeroOpenDotaMapper::class);

        $this->provider = new OpenDotaTeamProvider(
            gateway: $this->gateway,
            teamMapper: $this->teamMapper,
            matchesMapper: $this->matchesMapper,
            playerMapper: $this->playerMapper,
            heroMapper: $this->heroMapper
        );
    }

    public function test_getTeamsReturnsTeams()
    {
        $tsTeamId = new TeamId(7119388);
        $bbTeamId = new TeamId(8255888);
        $auroraTeamId = new TeamId(9467224);

        $tsTeam = $this->createTestTeam($tsTeamId);
        $bbTeam = $this->createTestTeam($bbTeamId, 'BetBoom Team', 'BetBoom');
        $auroraTeam = $this->createTestTeam($auroraTeamId, 'Aurora Gaming', 'Aurora');
        $expectedTeams = [$tsTeam, $bbTeam, $auroraTeam];

        $apiResponse = [];
        foreach ($expectedTeams as $team) {
            $apiResponse[] = $this->createGetTeamApiResponse($team);
        }

        $this->gateway
            ->expects($this->once())
            ->method('get')
            ->with('teams')
            ->willReturn($apiResponse);

        $this->teamMapper
            ->expects($this->once())
            ->method('toCollection')
            ->with($apiResponse)
            ->willReturn($expectedTeams);

        $result = $this->provider->getTeams();

        $this->assertEquals($expectedTeams, $result);
    }

    public function test_getTeamsReturnsApiFails()
    {
        $exception = new ApiGatewayException('Network error.');
        $this->gateway
            ->expects($this->once())
            ->method('get')
            ->with('teams')
            ->willThrowException($exception);

        $this->expectException(ApiGatewayException::class);
        $this->expectExceptionMessage($exception->getMessage());

        $this->provider->getTeams();
    }

    public function test_getTeamsReturnsMapperFails()
    {
        $tsTeamId = new TeamId(7119388);
        $bbTeamId = new TeamId(8255888);
        $auroraTeamId = new TeamId(9467224);

        $tsTeam = $this->createTestTeam($tsTeamId);
        $bbTeam = $this->createTestTeam($bbTeamId, 'BetBoom Team', 'BetBoom');
        $auroraTeam = $this->createTestTeam($auroraTeamId, 'Aurora Gaming', 'Aurora');
        $expectedTeams = [$tsTeam, $bbTeam, $auroraTeam];

        $apiResponse = [];
        foreach ($expectedTeams as $team) {
            $apiResponse[] = $this->createGetTeamApiResponse($team);
        }

        $this->gateway
            ->expects($this->once())
            ->method('get')
            ->with('teams')
            ->willReturn($apiResponse);

        $exception = new MappingException('Missing required field "rating" in API response');

        $this->teamMapper
            ->expects($this->once())
            ->method('toCollection')
            ->with($apiResponse)
            ->willThrowException($exception);

        $this->expectException(MappingException::class);
        $this->expectExceptionMessage($exception->getMessage());

        $this->provider->getTeams();
    }

    public function test_getTeamReturnsTeam()
    {
        $teamId = new TeamId(7119388);
        $expectedTeam = $this->createTestTeam($teamId);
        $apiResponse = $this->createGetTeamApiResponse($expectedTeam);

        $this->gateway
            ->expects($this->once())
            ->method('get')
            ->with(sprintf('teams/%s', $teamId->getValue()))
            ->willReturn($apiResponse);

        $this->teamMapper
            ->expects($this->once())
            ->method('toEntity')
            ->with($apiResponse)
            ->willReturn($expectedTeam);

        $result = $this->provider->getTeam($teamId);

        $this->assertSame($expectedTeam, $result);
    }

    public function test_getTeamReturnsTeamWhenApiFails()
    {
        $teamId = new TeamId(7119388);
        $exception = new ApiGatewayException('Network error.');

        $this->gateway
            ->expects($this->once())
            ->method('get')
            ->with(sprintf('teams/%s', $teamId->getValue()))
            ->willThrowException($exception);

        $this->expectException(ApiGatewayException::class);
        $this->expectExceptionMessage($exception->getMessage());

        $this->provider->getTeam($teamId);
    }

    public function test_getTeamReturnsTeamWhenMapperException()
    {
        $teamId = new TeamId(7119388);
        $team = $this->createTestTeam($teamId);
        $apiResponse = $this->createGetTeamApiResponse($team);

        $this->gateway
            ->expects($this->once())
            ->method('get')
            ->with(sprintf('teams/%s', $teamId->getValue()))
            ->willReturn($apiResponse);

        $exception = new MappingException('Missing required field "rating" in API response');

        $this->teamMapper
            ->expects($this->once())
            ->method('toEntity')
            ->with($apiResponse)
            ->willThrowException($exception);

        $this->expectException(MappingException::class);
        $this->expectExceptionMessage($exception->getMessage());

        $this->provider->getTeam($teamId);
    }

    private function createTestTeam(TeamId $id, string $name = 'Team Spirit', string $tag = 'TSpirit'): Team
    {
        return new Team(
            id: $id,
            name: new TeamName($name),
            tag: new TeamTag($tag),
            stats: TeamStats::fromApi(887, 604, 1369.62),
            lastMatchTime: new DateTimeImmutable('@1777031654')
        );
    }

    private function createGetTeamApiResponse(Team $team): array
    {
        return [
            'team_id' => $team->getId()->toInt(),
            'rating' => $team->getStats()->getRating(),
            'wins' => $team->getStats()->getWins(),
            'losses' => $team->getStats()->getLosses(),
            'last_match_time' => $team->getLastMatchTime()->getTimestamp(),
            'delta' => -13.5074,
            'match_id' => 8784047386,
            'name' => $team->getName()->getValue(),
            'tag' => $team->getTag()->getValue(),
            'logo_url' => 'https://cdn.steamusercontent.com/ugc/1839179120711951766/CD7E0885CB527334205CC7885E9C101B7BC17702/'
        ];
    }
}