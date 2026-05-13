<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Domain\Entities\Match\ValueObjects;

final readonly class MatchResult
{
    public function __construct(
        private TeamSide $winner,
        private TeamSide $loser,
    )
    {
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