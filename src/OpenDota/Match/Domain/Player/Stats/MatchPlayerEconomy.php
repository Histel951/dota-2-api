<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\OpenDota\Match\Domain\Player\Stats;

use Histel951\Dota2Api\SharedKernel\GPM;
use Histel951\Dota2Api\SharedKernel\XPM;

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