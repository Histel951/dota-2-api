<?php
declare(strict_types=1);

namespace Histel951\Dota2Api\Team\Port;

use Histel951\Dota2Api\Exception\SourceDataException;
use Histel951\Dota2Api\Exception\SourceRequestException;
use Histel951\Dota2Api\SharedKernel\TeamId;
use Histel951\Dota2Api\Team\Domain\Heroes\TeamHero;
use Histel951\Dota2Api\Team\Domain\History\TeamMatch;
use Histel951\Dota2Api\Team\Domain\Roster\TeamPlayer;
use Histel951\Dota2Api\Team\Domain\Team;

interface TeamSourceInterface
{
    /**
     * GET team info by id
     *
     * @throws SourceRequestException|SourceDataException
     */
    public function getTeam(TeamId $id): Team;

    /**
     * GET teams
     *
     * @return list<Team>
     * @throws SourceRequestException|SourceDataException
     */
    public function getTeams(): array;

    /**
     * GET team matches
     *
     * @return list<TeamMatch>
     * @throws SourceRequestException|SourceDataException
     */
    public function getMatchesByTeam(TeamId $teamId): array;

    /**
     * GET players played in team
     *
     * @return list<TeamPlayer>
     * @throws SourceRequestException|SourceDataException
     */
    public function getPlayersByTeam(TeamId $teamId): array;

    /**
     * GET most popular heroes by team
     *
     * @return list<TeamHero>
     * @throws SourceRequestException|SourceDataException
     */
    public function getHeroesByTeam(TeamId $teamId): array;
}
