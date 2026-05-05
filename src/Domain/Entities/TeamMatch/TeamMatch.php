<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Domain\Entities\TeamMatch;

use DateTimeImmutable;
use Histel951\Dota2Api\Domain\Common\ValueObjects\Cluster;
use Histel951\Dota2Api\Domain\Common\ValueObjects\LeagueId;
use Histel951\Dota2Api\Domain\Common\ValueObjects\LeagueName;
use Histel951\Dota2Api\Domain\Common\ValueObjects\MatchId;
use Histel951\Dota2Api\Domain\Common\ValueObjects\MatchOutcome;
use Histel951\Dota2Api\Domain\Common\ValueObjects\TeamId;
use Histel951\Dota2Api\Domain\Common\ValueObjects\TeamName;

final readonly class TeamMatch
{
    public function __construct(
        private MatchId           $matchId,
        private MatchOutcome      $matchOutcome,
        private DateTimeImmutable $startTime,
        private Cluster           $matchCluster,
        private LeagueId          $leagueId,
        private LeagueName        $leagueName,
        private TeamId            $opposingTeamId,
        private ?TeamName         $opposingTeamName,
    )
    {
    }

    public function getMatchId(): MatchId
    {
        return $this->matchId;
    }

    public function matchOutcome(): MatchOutcome
    {
        return $this->matchOutcome;
    }

    public function getStartTime(): DateTimeImmutable
    {
        return $this->startTime;
    }

    public function getMatchCluster(): Cluster
    {
        return $this->matchCluster;
    }

    public function getLeagueId(): LeagueId
    {
        return $this->leagueId;
    }

    public function getLeagueName(): LeagueName
    {
        return $this->leagueName;
    }

    public function getOpposingTeamId(): TeamId
    {
        return $this->opposingTeamId;
    }

    public function getOpposingTeamName(): ?TeamName
    {
        return $this->opposingTeamName;
    }
}