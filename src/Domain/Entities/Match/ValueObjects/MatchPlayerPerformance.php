<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Domain\Entities\Match\ValueObjects;

use Histel951\Dota2Api\Domain\Common\ValueObjects\KDA;
use Histel951\Dota2Api\Domain\Common\ValueObjects\Role;

final readonly class MatchPlayerPerformance
{
    public function __construct(
        private Identity               $identity,
        private KDA                    $kda,
        private Role                   $role,
        private ObjectivesStats        $objectives,
        private CreepsStats            $creeps,
        private MatchPlayerEconomy     $economy,
        private WardingStats           $warding,
        private DamageStats            $damage,
        private UtilityStats           $utility,
    )
    {
    }

    public function withRole(Role $role): self
    {
        return new self(
            identity: $this->identity,
            kda: $this->kda,
            role: $role,
            objectives: $this->objectives,
            creeps: $this->creeps,
            economy: $this->economy,
            warding: $this->warding,
            damage: $this->damage,
            utility: $this->utility,
        );
    }

    public function getKda(): KDA
    {
        return $this->kda;
    }

    public function getRole(): Role
    {
        return $this->role;
    }

    public function getObjectives(): ObjectivesStats
    {
        return $this->objectives;
    }

    public function getCreeps(): CreepsStats
    {
        return $this->creeps;
    }

    public function getEconomy(): MatchPlayerEconomy
    {
        return $this->economy;
    }

    public function getWarding(): WardingStats
    {
        return $this->warding;
    }

    public function getDamage(): DamageStats
    {
        return $this->damage;
    }

    public function getIdentity(): Identity
    {
        return $this->identity;
    }

    public function getUtility(): UtilityStats
    {
        return $this->utility;
    }
}