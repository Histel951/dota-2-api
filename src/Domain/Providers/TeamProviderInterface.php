<?php

namespace Histel951\Dota2Api\Domain\Providers;

use Histel951\Dota2Api\Domain\Common\ValueObjects\TeamId;
use Histel951\Dota2Api\Domain\Entities\Team\Team;
use Histel951\Dota2Api\Domain\Entities\TeamHero\TeamHero;
use Histel951\Dota2Api\Domain\Entities\TeamMatch\TeamMatch;
use Histel951\Dota2Api\Domain\Entities\TeamPlayer\TeamPlayer;

interface TeamProviderInterface
{
    /**
     * GET team info by id
     *
     * @param TeamId $id
     * @return Team
     */
    public function getTeam(TeamId $id): Team;

    /**
     * GET teams
     *
     * @return Team[]
     */
    public function getTeams(): array;

    /**
     * GET team matches
     *
     * @param TeamId $teamId
     * @return TeamMatch[]
     */
    public function getMatchesByTeam(TeamId $teamId): array;

    /**
     * GET players played in team
     *
     * @param TeamId $teamId
     * @return TeamPlayer[]
     */
    public function getPlayersByTeam(TeamId $teamId): array;

    /**
     * GET most popular heroes by team
     *
     * @param TeamId $teamId
     * @return TeamHero[]
     */
    public function getHeroesByTeam(TeamId $teamId): array;
}