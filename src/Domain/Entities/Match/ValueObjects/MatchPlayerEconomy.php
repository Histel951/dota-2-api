<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Domain\Entities\Match\ValueObjects;

use Histel951\Dota2Api\Domain\Common\ValueObjects\GoldSpent;
use Histel951\Dota2Api\Domain\Common\ValueObjects\GPM;
use Histel951\Dota2Api\Domain\Common\ValueObjects\NetWorth;
use Histel951\Dota2Api\Domain\Common\ValueObjects\TotalGold;
use Histel951\Dota2Api\Domain\Common\ValueObjects\TotalXP;
use Histel951\Dota2Api\Domain\Common\ValueObjects\XPM;

final readonly class MatchPlayerEconomy
{
    public function __construct(
        private NetWorth $netWorth,
        private TotalGold $totalGold,
        private TotalXP $totalXP,
        private GoldSpent $goldSpent,
        private XPM $XPM,
        private GPM $GPM,
    )
    {
    }

    public function getNetWorth(): NetWorth
    {
        return $this->netWorth;
    }

    public function getTotalGold(): TotalGold
    {
        return $this->totalGold;
    }

    public function getTotalXP(): TotalXP
    {
        return $this->totalXP;
    }

    public function getGoldSpent(): GoldSpent
    {
        return $this->goldSpent;
    }

    public function getXPM(): XPM
    {
        return $this->XPM;
    }

    public function getGPM(): GPM
    {
        return $this->GPM;
    }
}