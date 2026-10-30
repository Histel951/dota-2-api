<?php
declare(strict_types=1);

namespace Histel951\Dota2Api\Match\Domain\Side;

use Histel951\Dota2Api\Match\Domain\Draft\Draft;
use Histel951\Dota2Api\Match\Domain\Player\MatchPlayers;
use Histel951\Dota2Api\SharedKernel\TeamId;
use Histel951\Dota2Api\SharedKernel\TeamName;

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

    /**
     * Копия стороны с другим составом игроков (например, с проставленными ролями).
     */
    public function withPlayers(MatchPlayers $players): self
    {
        return new self(
            teamId: $this->teamId,
            teamName: $this->teamName,
            won: $this->won,
            draft: $this->draft,
            players: $players,
            side: $this->side,
        );
    }

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