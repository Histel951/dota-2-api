<?php

namespace Histel951\Dota2Api\Domain\Entities\Match;

use Histel951\Dota2Api\Domain\Common\ValueObjects\Duration;
use Histel951\Dota2Api\Domain\Common\ValueObjects\MatchId;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\Draft;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\MatchPlayers;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\MatchResult;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\TeamSide;

final class MatchDetail
{
    public function __construct(
        private readonly MatchId $id,
        private readonly Duration $duration,
        private readonly MatchResult $result,
        private readonly TeamSide $radiant,
        private readonly TeamSide $dire,
        private readonly Draft $draft,
        private readonly MatchPlayers $players,
    ) {}

    public function getId(): MatchId
    {
        return $this->id;
    }

    public function getDuration(): Duration
    {
        return $this->duration;
    }

    public function getResult(): MatchResult
    {
        return $this->result;
    }

    public function getRadiant(): TeamSide
    {
        return $this->radiant;
    }

    public function getDire(): TeamSide
    {
        return $this->dire;
    }

    public function getDraft(): Draft
    {
        return $this->draft;
    }

    public function getPlayers(): MatchPlayers
    {
        return $this->players;
    }
}