<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Stratz\Match\Domain\Player\Stats;

final readonly class Damage
{
    public function __construct(
        private HeroDamage $heroDamage,
        private TowerDamage $towerDamage,
        private HeroHealing $heroHealing,
    )
    {
    }

    public function getHeroDamage(): HeroDamage
    {
        return $this->heroDamage;
    }

    public function getTowerDamage(): TowerDamage
    {
        return $this->towerDamage;
    }

    public function getHeroHealing(): HeroHealing
    {
        return $this->heroHealing;
    }
}
