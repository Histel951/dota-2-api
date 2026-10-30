<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Stratz\Team\Domain\Heroes;

use Histel951\Dota2Api\SharedKernel\HeroId;
use Histel951\Dota2Api\SharedKernel\HeroName;
use Histel951\Dota2Api\SharedKernel\WinrateStats;

final readonly class TeamHero
{
    public function __construct(
        private HeroId       $heroId,
        private HeroName     $name,
        private WinrateStats $stats,
    )
    {
    }

    public function getHeroId(): HeroId
    {
        return $this->heroId;
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
