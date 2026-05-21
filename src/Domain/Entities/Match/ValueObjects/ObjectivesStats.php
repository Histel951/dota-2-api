<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Domain\Entities\Match\ValueObjects;

final readonly class ObjectivesStats
{
    public function __construct(
        private RoshanKills $roshanKills,
        private TowerKills $towerKills,
        private RunePickups $runePickups,
    )
    {
    }

    public function getRoshanKills(): RoshanKills
    {
        return $this->roshanKills;
    }

    public function getTowerKills(): TowerKills
    {
        return $this->towerKills;
    }

    public function getRunePickups(): RunePickups
    {
        return $this->runePickups;
    }
}