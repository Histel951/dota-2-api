<?php

namespace Histel951\Dota2Api\Domain\Entities\Match\ValueObjects;

use Histel951\Dota2Api\Domain\Common\ValueObjects\TeamId;
use Histel951\Dota2Api\Domain\Common\ValueObjects\TeamName;
use Histel951\Dota2Api\Domain\Entities\Match\Enums\Side;

final readonly class TeamSide
{
    /**
     * @param TeamId $teamId
     * @param TeamName $teamName
     * @param bool $won
     * @param Draft $draft
     * @param MatchPlayers $players
     * @param Side $side
     */
    public function __construct(
        private TeamId       $teamId,
        private TeamName     $teamName,
        private bool         $won,
        private Draft        $draft,
        private MatchPlayers $players,
        private Side         $side,
    ) {}

    public function getTeamId(): TeamId
    {
        return $this->teamId;
    }

    public function isWon(): bool
    {
        return $this->won;
    }

    public function getDraft(): Draft
    {
        return $this->draft;
    }

    public function getPlayers(): MatchPlayers
    {
        return $this->players;
    }

    public function getTeamName(): TeamName
    {
        return $this->teamName;
    }

    public function getSide(): Side
    {
        return $this->side;
    }

    public function isRadiant(): bool
    {
        return $this->side === Side::RADIANT;
    }

    public function isDire(): bool
    {
        return $this->side === Side::DIRE;
    }
}