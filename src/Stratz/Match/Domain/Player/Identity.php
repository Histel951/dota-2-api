<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Stratz\Match\Domain\Player;

use Histel951\Dota2Api\SharedKernel\HeroId;
use Histel951\Dota2Api\SharedKernel\PlayerId;
use Histel951\Dota2Api\SharedKernel\PlayerName;
use Histel951\Dota2Api\SharedKernel\Side;

final readonly class Identity
{
    public function __construct(
        private PlayerId $playerId,
        private HeroId $heroId,
        private Side $side,
        private Lane $lane,
        private ?Position $position,
        private ?PlayerName $proName,
        private ?PlayerName $personaName,
    )
    {
    }

    public function getPlayerId(): PlayerId
    {
        return $this->playerId;
    }

    public function getHeroId(): HeroId
    {
        return $this->heroId;
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

    public function getLane(): Lane
    {
        return $this->lane;
    }

    /**
     * Позиция 1-5; null, если источник её не определил (нераспарсенный матч)
     */
    public function getPosition(): ?Position
    {
        return $this->position;
    }

    public function getProName(): ?PlayerName
    {
        return $this->proName;
    }

    public function getPersonaName(): ?PlayerName
    {
        return $this->personaName;
    }
}
