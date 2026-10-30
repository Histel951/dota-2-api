<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\OpenDota\Match\Domain\Side;

final readonly class MatchResult
{
    public function __construct(
        private TeamSide $winner,
        private TeamSide $loser,
    )
    {
    }

    /**
     * Победитель определяется по флагу isWon() у сторон.
     */
    public static function fromSides(TeamSide $radiant, TeamSide $dire): self
    {
        return $radiant->isWon()
            ? new self(winner: $radiant, loser: $dire)
            : new self(winner: $dire, loser: $radiant);
    }

    public function getWinner(): TeamSide
    {
        return $this->winner;
    }

    public function getLoser(): TeamSide
    {
        return $this->loser;
    }
}