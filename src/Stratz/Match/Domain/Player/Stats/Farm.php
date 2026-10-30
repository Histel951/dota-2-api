<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Stratz\Match\Domain\Player\Stats;

final readonly class Farm
{
    public function __construct(
        private LastHits $lastHits,
        private Denies $denies,
    )
    {
    }

    public function getLastHits(): LastHits
    {
        return $this->lastHits;
    }

    public function getDenies(): Denies
    {
        return $this->denies;
    }
}
