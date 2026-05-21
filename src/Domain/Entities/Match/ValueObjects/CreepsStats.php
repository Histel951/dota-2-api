<?php

namespace Histel951\Dota2Api\Domain\Entities\Match\ValueObjects;

final readonly class CreepsStats
{
    public function __construct(
        private LastHits $lastHits,
        private LaneCreeps $laneCreeps,
        private Denies $denies,
        private NeutralCreeps $neutralCreeps,
        private AncientCreeps $ancientCreeps,
    )
    {
    }

    public function getLastHits(): LastHits
    {
        return $this->lastHits;
    }

    public function getLaneCreeps(): LaneCreeps
    {
        return $this->laneCreeps;
    }

    public function getDenies(): Denies
    {
        return $this->denies;
    }

    public function getNeutralCreeps(): NeutralCreeps
    {
        return $this->neutralCreeps;
    }

    public function getAncientCreeps(): AncientCreeps
    {
        return $this->ancientCreeps;
    }
}