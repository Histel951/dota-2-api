<?php
declare(strict_types=1);

namespace Histel951\Dota2Api\Infrastructure\OpenDota\Team;

use Histel951\Dota2Api\SharedKernel\TeamId;
use Histel951\Dota2Api\Team\Domain\Team;
use Histel951\Dota2Api\Team\Domain\Heroes\TeamHero;
use Histel951\Dota2Api\Team\Domain\History\TeamMatch;
use Histel951\Dota2Api\Team\Domain\Roster\TeamPlayer;
use Histel951\Dota2Api\Team\Port\TeamSourceInterface;
use Histel951\Dota2Api\Exception\SourceRequestException;
use Histel951\Dota2Api\Exception\SourceDataException;
use Histel951\Dota2Api\Infrastructure\Http\ApiGatewayInterface;

final class OpenDotaTeamSource implements TeamSourceInterface
{
    public function __construct(
        private readonly ApiGatewayInterface      $gateway,
        private readonly TeamMapper       $teamMapper,
        private readonly TeamMatchMapper  $matchesMapper,
        private readonly TeamPlayerMapper $playerMapper,
        private readonly TeamHeroMapper   $heroMapper,
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