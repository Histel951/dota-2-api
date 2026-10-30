<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Stratz\Team\Domain\Roster;

use Histel951\Dota2Api\SharedKernel\PlayerId;
use Histel951\Dota2Api\SharedKernel\PlayerName;
use Histel951\Dota2Api\SharedKernel\WinrateStats;

final readonly class TeamPlayer
{
    public function __construct(
        private PlayerId     $playerId,
        private ?PlayerName  $name,
        private WinrateStats $stats,
        private bool         $isCurrentMember,
    )
    {
    }

    public function getPlayerId(): PlayerId
    {
        return $this->playerId;
    }

    public function getName(): ?PlayerName
    {
        return $this->name;
    }

    public function getStats(): WinrateStats
    {
        return $this->stats;
    }

    public function isCurrentMember(): bool
    {
        return $this->isCurrentMember;
    }
}
