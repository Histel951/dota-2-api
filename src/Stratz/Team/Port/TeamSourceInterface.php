<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Stratz\Team\Port;

use Histel951\Dota2Api\Exception\SourceDataException;
use Histel951\Dota2Api\Exception\SourceRequestException;
use Histel951\Dota2Api\SharedKernel\TeamId;
use Histel951\Dota2Api\Stratz\Team\Domain\Heroes\TeamHero;
use Histel951\Dota2Api\Stratz\Team\Domain\History\TeamMatch;
use Histel951\Dota2Api\Stratz\Team\Domain\Roster\TeamPlayer;
use Histel951\Dota2Api\Stratz\Team\Domain\Team;

interface TeamSourceInterface
{
    /**
     * GraphQL: team(teamId) — информация о команде.
     *
     * @throws SourceRequestException|SourceDataException
     */
    public function getTeam(TeamId $id): Team;

    /**
     * GraphQL: team.matches — последние матчи команды.
     *
     * @return list<TeamMatch>
     * @throws SourceRequestException|SourceDataException
     */
    public function getMatchesByTeam(TeamId $teamId): array;

    /**
     * GraphQL: team.matchesGroupBy(STEAM_ACCOUNT_ID) — игроки, игравшие за команду.
     *
     * @return list<TeamPlayer>
     * @throws SourceRequestException|SourceDataException
     */
    public function getPlayersByTeam(TeamId $teamId): array;

    /**
     * GraphQL: team.matchesGroupBy(HERO) — герои команды.
     *
     * @return list<TeamHero>
     * @throws SourceRequestException|SourceDataException
     */
    public function getHeroesByTeam(TeamId $teamId): array;
}
