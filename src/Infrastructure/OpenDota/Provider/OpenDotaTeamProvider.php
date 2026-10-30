<?php
declare(strict_types=1);

namespace Histel951\Dota2Api\Infrastructure\OpenDota\Provider;

use Histel951\Dota2Api\SharedKernel\TeamId;
use Histel951\Dota2Api\Domain\Entities\Team\Team;
use Histel951\Dota2Api\Domain\Entities\TeamHero\TeamHero;
use Histel951\Dota2Api\Domain\Entities\TeamMatch\TeamMatch;
use Histel951\Dota2Api\Domain\Entities\TeamPlayer\TeamPlayer;
use Histel951\Dota2Api\Domain\Providers\TeamProviderInterface;
use Histel951\Dota2Api\Exception\SourceRequestException;
use Histel951\Dota2Api\Exception\SourceDataException;
use Histel951\Dota2Api\Infrastructure\Http\ApiGatewayInterface;
use Histel951\Dota2Api\Infrastructure\OpenDota\Mapper\TeamHeroOpenDotaMapper;
use Histel951\Dota2Api\Infrastructure\OpenDota\Mapper\TeamMatchOpenDotaMapper;
use Histel951\Dota2Api\Infrastructure\OpenDota\Mapper\TeamOpenDotaMapper;
use Histel951\Dota2Api\Infrastructure\OpenDota\Mapper\TeamPlayerOpenDotaMapper;

class OpenDotaTeamProvider implements TeamProviderInterface
{
    public function __construct(
        private readonly ApiGatewayInterface      $gateway,
        private readonly TeamOpenDotaMapper       $teamMapper,
        private readonly TeamMatchOpenDotaMapper  $matchesMapper,
        private readonly TeamPlayerOpenDotaMapper $playerMapper,
        private readonly TeamHeroOpenDotaMapper   $heroMapper,
    )
    {
    }

    /**
     * @return Team[]
     * @throws SourceRequestException
     *
     * @throws SourceDataException
     */
    public function getTeams(): array
    {
        $data = $this->gateway->get('teams');
        return $this->teamMapper->toCollection($data);
    }

    /**
     * @throws SourceDataException
     * @throws SourceRequestException
     */
    public function getTeam(TeamId $id): Team
    {
        $data = $this->gateway->get(sprintf('teams/%s', $id->getValue()));
        return $this->teamMapper->toEntity($data);
    }

    /**
     * @return TeamMatch[]
     * @throws SourceDataException
     *
     * @throws SourceRequestException
     */
    public function getMatchesByTeam(TeamId $teamId): array
    {
        $data = $this->gateway->get(sprintf('teams/%s/matches', $teamId->getValue()));
        return $this->matchesMapper->toCollection($data);
    }

    /**
     * @return TeamPlayer[]
     * @throws SourceDataException
     *
     * @throws SourceRequestException
     */
    public function getPlayersByTeam(TeamId $teamId): array
    {
        $data = $this->gateway->get(sprintf('teams/%s/players', $teamId->getValue()));
        return $this->playerMapper->toCollection($data);
    }

    /**
     * @return TeamHero[]
     * @throws SourceDataException
     *
     * @throws SourceRequestException
     */
    public function getHeroesByTeam(TeamId $teamId): array
    {
        $data = $this->gateway->get(sprintf('teams/%s/heroes', $teamId->getValue()));
        return $this->heroMapper->toCollection($data);
    }
}