<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Stratz\Team\Domain\History;

use DateTimeImmutable;
use Histel951\Dota2Api\SharedKernel\Cluster;
use Histel951\Dota2Api\SharedKernel\Duration;
use Histel951\Dota2Api\SharedKernel\LeagueId;
use Histel951\Dota2Api\SharedKernel\LeagueName;
use Histel951\Dota2Api\SharedKernel\MatchId;
use Histel951\Dota2Api\SharedKernel\Side;
use Histel951\Dota2Api\SharedKernel\TeamId;
use Histel951\Dota2Api\SharedKernel\TeamName;

final readonly class TeamMatch
{
    public function __construct(
        private MatchId           $matchId,
        private Side              $side,
        private bool              $won,
        private ?Score            $score,
        private DateTimeImmutable $startTime,
        private Duration          $duration,
        private Cluster           $cluster,
        private LeagueId          $leagueId,
        private ?LeagueName       $leagueName,
        private TeamId            $opposingTeamId,
        private ?TeamName         $opposingTeamName,
    )
    {
    }

    public function getMatchId(): MatchId
    {
        return $this->matchId;
    }

    /**
     * Сторона, за которую играла команда
     */
    public function getSide(): Side
    {
        return $this->side;
    }

    public function isWon(): bool
    {
        return $this->won;
    }

    public function getScore(): ?Score
    {
        return $this->score;
    }

    public function getStartTime(): DateTimeImmutable
    {
        return $this->startTime;
    }

    public function getDuration(): Duration
    {
        return $this->duration;
    }

    public function getCluster(): Cluster
    {
        return $this->cluster;
    }

    public function getLeagueId(): LeagueId
    {
        return $this->leagueId;
    }

    public function getLeagueName(): ?LeagueName
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
