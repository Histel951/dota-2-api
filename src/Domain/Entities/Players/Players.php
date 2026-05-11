<?php
declare(strict_types=1);

namespace Histel951\Dota2Api\Domain\Entities\Players;

use Histel951\Dota2Api\Domain\Common\ValueObjects\PlayerId;
use Histel951\Dota2Api\Domain\Common\ValueObjects\HeroId;
use Histel951\Dota2Api\Domain\Common\ValueObjects\HeroName;
use Histel951\Dota2Api\Domain\Common\ValueObjects\WinrateStats;



final class Players
{
    public function __construct(
        private readonly PlayerId $id,
        private readonly HeroId $heroId,
        private readonly HeroName $name,
        private readonly WinrateStats $winrateStats,
    )
    {
    }

    public function getId(): PlayerId
    {
        return $this->id;
    }

    public function getHeroId(): HeroId
    {
        return $this->heroId;
    }

    public function getName(): HeroName
    {
        return $this->name;
    }

    public function getWinrateStats(): WinrateStats
    {
        return $this->winrateStats;
    }
}



