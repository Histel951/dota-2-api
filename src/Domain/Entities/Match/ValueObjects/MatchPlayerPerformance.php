<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Domain\Entities\Match\ValueObjects;

use Histel951\Dota2Api\Domain\Common\ValueObjects\GPM;
use Histel951\Dota2Api\Domain\Common\ValueObjects\HeroId;
use Histel951\Dota2Api\Domain\Common\ValueObjects\KDA;
use Histel951\Dota2Api\Domain\Common\ValueObjects\PlayerId;
use Histel951\Dota2Api\Domain\Common\ValueObjects\PlayerName;
use Histel951\Dota2Api\Domain\Common\ValueObjects\Role;
use Histel951\Dota2Api\Domain\Common\ValueObjects\XPM;
use Histel951\Dota2Api\Domain\Entities\Match\Enums\SideEnum;

final readonly class MatchPlayerPerformance
{
    public function __construct(
        private PlayerId        $playerId,
        private HeroId          $heroId,
        private KDA             $kda,
        private GPM             $gpm,
        private XPM             $xpm,
        private MatchPlayerLane $lane,
        private Role            $role,
        private SideEnum        $side,
        private ?PlayerName     $playerProName,
        private PlayerName      $playerPersonaName,
    )
    {
    }

    public function withRole(Role $role): self
    {
        return new self(
            playerId: $this->playerId,
            heroId: $this->heroId,
            kda: $this->kda,
            gpm: $this->gpm,
            xpm: $this->xpm,
            lane: $this->lane,
            role: $role,
            side: $this->side,
            playerProName: $this->playerProName,
            playerPersonaName: $this->playerPersonaName,
        );
    }

    public function getPlayerId(): PlayerId
    {
        return $this->playerId;
    }

    public function getHeroId(): HeroId
    {
        return $this->heroId;
    }

    public function getKda(): KDA
    {
        return $this->kda;
    }

    public function getGpm(): GPM
    {
        return $this->gpm;
    }

    public function getXpm(): XPM
    {
        return $this->xpm;
    }

    public function getLane(): MatchPlayerLane
    {
        return $this->lane;
    }

    public function getRole(): Role
    {
        return $this->role;
    }

    public function isRadiant(): bool
    {
        return $this->side === SideEnum::RADIANT;
    }

    public function isDire(): bool
    {
        return $this->side === SideEnum::DIRE;
    }

    public function getPlayerProName(): ?PlayerName
    {
        return $this->playerProName;
    }

    public function getPlayerName(): PlayerName
    {
        return $this->playerPersonaName;
    }
}