<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Domain\Entities\Match\ValueObjects;

use Histel951\Dota2Api\SharedKernel\HeroId;
use Histel951\Dota2Api\SharedKernel\PlayerId;
use Histel951\Dota2Api\SharedKernel\PlayerName;
use Histel951\Dota2Api\Domain\Entities\Match\Enums\Side;

final readonly class Identity
{
    public function __construct(
        private PlayerId $playerId,
        private HeroId $heroId,
        private Side $side,
        private MatchPlayerLane $lane,
        private PlayerName $playerProName,
        private PlayerName $playerPersonaName,
    )
    {
    }

    public function isRadiant(): bool
    {
        return $this->side === Side::RADIANT;
    }

    public function isDire(): bool
    {
        return $this->side === Side::DIRE;
    }

    public function getSide(): Side
    {
        return $this->side;
    }

    public function getPlayerProName(): ?PlayerName
    {
        return $this->playerProName;
    }

    public function getPlayerName(): PlayerName
    {
        return $this->playerPersonaName;
    }

    public function getLane(): MatchPlayerLane
    {
        return $this->lane;
    }

    public function getPlayerId(): PlayerId
    {
        return $this->playerId;
    }

    public function getHeroId(): HeroId
    {
        return $this->heroId;
    }
}