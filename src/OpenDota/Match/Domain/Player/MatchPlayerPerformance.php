<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\OpenDota\Match\Domain\Player;

use Histel951\Dota2Api\OpenDota\Match\Domain\Player\Stats\CreepsStats;
use Histel951\Dota2Api\OpenDota\Match\Domain\Player\Stats\DamageStats;
use Histel951\Dota2Api\OpenDota\Match\Domain\Player\Stats\MatchPlayerEconomy;
use Histel951\Dota2Api\OpenDota\Match\Domain\Player\Stats\ObjectivesStats;
use Histel951\Dota2Api\OpenDota\Match\Domain\Player\Stats\UtilityStats;
use Histel951\Dota2Api\OpenDota\Match\Domain\Player\Stats\WardingStats;
use Histel951\Dota2Api\SharedKernel\KDA;

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