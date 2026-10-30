<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\OpenDota\Team\Domain\Roster;

use Histel951\Dota2Api\SharedKernel\PlayerId;
use Histel951\Dota2Api\SharedKernel\PlayerName;
use Histel951\Dota2Api\SharedKernel\WinrateStats;

final readonly class TeamPlayer
{
    public function __construct(
        private PlayerId     $playerId,
        private ?PlayerName  $playerProName,
        private WinrateStats $playerStats,
        private bool         $isCurrentTeamMember,
    )
    {
    }

    public function getPlayerId(): PlayerId
    {
        return $this->playerId;
    }

    public function getPlayerProName(): ?PlayerName
    {
        return $this->playerProName;
    }

    public function getPlayerStats(): WinrateStats
    {
        return $this->playerStats;
    }

    public function isCurrentTeamMember(): bool
    {
        return $this->isCurrentTeamMember;
    }
}