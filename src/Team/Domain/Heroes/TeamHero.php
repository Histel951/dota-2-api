<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Team\Domain\Heroes;

use Histel951\Dota2Api\SharedKernel\HeroId;
use Histel951\Dota2Api\Team\Domain\WinrateStats;

final readonly class TeamHero
{
    public function __construct(
        private HeroId       $id,
        private HeroName     $name,
        private WinrateStats $stats,
    )
    {
    }

    public function getId(): HeroId
    {
        return $this->id;
    }

    public function getName(): HeroName
    {
        return $this->name;
    }

    public function getStats(): WinrateStats
    {
        return $this->stats;
    }
}