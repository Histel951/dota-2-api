<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Tests\Unit\Infrastructure\OpenDota\Team;

use DateTimeImmutable;
use Histel951\Dota2Api\SharedKernel\TeamId;
use Histel951\Dota2Api\SharedKernel\TeamName;
use Histel951\Dota2Api\Team\Domain\Team;
use Histel951\Dota2Api\Team\Domain\TeamStats;
use Histel951\Dota2Api\Team\Domain\TeamTag;
use Histel951\Dota2Api\Exception\SourceRequestException;
use Histel951\Dota2Api\Exception\SourceDataException;
use Histel951\Dota2Api\Infrastructure\Http\ApiGateway;
use Histel951\Dota2Api\Infrastructure\OpenDota\Team\TeamHeroMapper;
use Histel951\Dota2Api\Infrastructure\OpenDota\Team\TeamMatchMapper;
use Histel951\Dota2Api\Infrastructure\OpenDota\Team\TeamMapper;
use Histel951\Dota2Api\Infrastructure\OpenDota\Team\TeamPlayerMapper;
use Histel951\Dota2Api\Infrastructure\OpenDota\Team\OpenDotaTeamSource;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Loader\Configurator\Traits\PropertyTrait;

class OpenDotaTeamSourceTest extends TestCase
{
    use PropertyTrait;

    private $gateway;
    private $teamMapper;
    private $matchesMapper;
    private $playerMapper;
    private $heroMapper;
    private OpenDotaTeamSource $provider;

    public function setUp(): void
    {
        $this->gateway = $this->createMock(ApiGateway::class);
        $this->teamMapper = $this->createMock(TeamMapper::class);
        $this->matchesMapper = $this->createMock(TeamMatchMapper::class);
        $this->playerMapper = $this->createMock(TeamPlayerMapper::class);
        $this->heroMapper = $this->createMock(TeamHeroMapper::class);

        $this->provider = new OpenDotaTeamSource(
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
        $exception = new SourceRequestException('Network error.');
        $this->gateway
            ->expects($this->once())
            ->method('get')
            ->with('teams')
            ->willThrowException($exception);

        $this->expectException(SourceRequestException::class);
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

        $exception = new SourceDataException('Missing required field "rating" in API response');

        $this->teamMapper
            ->expects($this->once())
            ->method('toCollection')
            ->with($apiResponse)
            ->willThrowException($exception);

        $this->expectException(SourceDataException::class);
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
        $exception = new SourceRequestException('Network error.');

        $this->gateway
            ->expects($this->once())
            ->method('get')
            ->with(sprintf('teams/%s', $teamId->getValue()))
            ->willThrowException($exception);

        $this->expectException(SourceRequestException::class);
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

        $exception = new SourceDataException('Missing required field "rating" in API response');

        $this->teamMapper
            ->expects($this->once())
            ->method('toEntity')
            ->with($apiResponse)
            ->willThrowException($exception);

        $this->expectException(SourceDataException::class);
        $this->expectExceptionMessage($exception->getMessage());

        $this->provider->getTeam($teamId);
    }

    private function createTestTeam(TeamId $id, string $name = 'Team Spirit', string $tag = 'TSpirit'): Team
    {
        return new Team(
            id: $id,
            name: new TeamName($name),
            tag: new TeamTag($tag),
            stats: TeamStats::fromRecord(887, 604, 1369.62),
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