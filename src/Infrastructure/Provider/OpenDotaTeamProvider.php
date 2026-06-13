<?php
declare(strict_types=1);

namespace Histel951\Dota2Api\Infrastructure\Provider;

use Histel951\Dota2Api\Domain\Common\ValueObjects\TeamId;
use Histel951\Dota2Api\Domain\Entities\Team\Team;
use Histel951\Dota2Api\Domain\Entities\TeamHero\TeamHero;
use Histel951\Dota2Api\Domain\Entities\TeamMatch\TeamMatch;
use Histel951\Dota2Api\Domain\Entities\TeamPlayer\TeamPlayer;
use Histel951\Dota2Api\Domain\Providers\TeamProviderInterface;
use Histel951\Dota2Api\Infrastructure\Exceptions\ApiGatewayException;
use Histel951\Dota2Api\Infrastructure\Exceptions\MappingException;
use Histel951\Dota2Api\Infrastructure\Http\Contracts\ApiGatewayInterface;
use Histel951\Dota2Api\Infrastructure\Mapper\Team\TeamHeroOpenDotaMapper;
use Histel951\Dota2Api\Infrastructure\Mapper\Team\TeamMatchOpenDotaMapper;
use Histel951\Dota2Api\Infrastructure\Mapper\Team\TeamOpenDotaMapper;
use Histel951\Dota2Api\Infrastructure\Mapper\Team\TeamPlayerOpenDotaMapper;

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
     * @throws ApiGatewayException
     *
     * @throws MappingException
     */
    public function getTeams(): array
    {
        $data = $this->gateway->get('teams');
        return $this->teamMapper->toCollection($data);
    }

    /**
     * @throws MappingException
     * @throws ApiGatewayException
     */
    public function getTeam(TeamId $id): Team
    {
        $data = $this->gateway->get(sprintf('teams/%s', $id->getValue()));
        return $this->teamMapper->toEntity($data);
    }

    /**
     * @return TeamMatch[]
     * @throws MappingException
     *
     * @throws ApiGatewayException
     */
    public function getMatchesByTeam(TeamId $teamId): array
    {
        $data = $this->gateway->get(sprintf('teams/%s/matches', $teamId->getValue()));
        return $this->matchesMapper->toCollection($data);
    }

    /**
     * @return TeamPlayer[]
     * @throws MappingException
     *
     * @throws ApiGatewayException
     */
    public function getPlayersByTeam(TeamId $teamId): array
    {
        $data = $this->gateway->get(sprintf('teams/%s/players', $teamId->getValue()));
        return $this->playerMapper->toCollection($data);
    }

    /**
     * @return TeamHero[]
     * @throws MappingException
     *
     * @throws ApiGatewayException
     */
    public function getHeroesByTeam(TeamId $teamId): array
    {
        $data = $this->gateway->get(sprintf('teams/%s/heroes', $teamId->getValue()));
        return $this->heroMapper->toCollection($data);
    }
}