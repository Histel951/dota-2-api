<?php
declare(strict_types=1);

namespace Histel951\Dota2Api\Domain\Entities\Match\ValueObjects;

final readonly class UtilityStats
{
    public function __construct(
        private BuybackCount           $buybackCount,
        private bool                   $firstBloodClaimed,
        private CourierKills           $courierKills,
        private Stuns                  $stuns,
        private TeamfightParticipation $teamfightParticipation,
    )
    {
    }

    public function getBuybackCount(): BuybackCount
    {
        return $this->buybackCount;
    }

    public function isFirstBloodClaimed(): bool
    {
        return $this->firstBloodClaimed;
    }

    public function getCourierKills(): CourierKills
    {
        return $this->courierKills;
    }

    public function getStuns(): Stuns
    {
        return $this->stuns;
    }

    public function getTeamfightParticipation(): TeamfightParticipation
    {
        return $this->teamfightParticipation;
    }
}