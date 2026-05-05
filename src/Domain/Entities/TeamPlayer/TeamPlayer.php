<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Domain\Entities\TeamPlayer;

use Histel951\Dota2Api\Domain\Common\ValueObjects\PlayerId;
use Histel951\Dota2Api\Domain\Common\ValueObjects\PlayerProName;
use Histel951\Dota2Api\Domain\Common\ValueObjects\WinrateStats;

final readonly class TeamPlayer
{
    public function __construct(
        private PlayerId      $playerId,
        private PlayerProName $playerProName,
        private WinrateStats  $playerStats,
        private bool          $isCurrentTeamMember,
    )
    {
    }

    public function getPlayerId(): PlayerId
    {
        return $this->playerId;
    }

    public function getPlayerProName(): PlayerProName
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