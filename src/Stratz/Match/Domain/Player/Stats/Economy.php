<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Stratz\Match\Domain\Player\Stats;

use Histel951\Dota2Api\SharedKernel\GPM;
use Histel951\Dota2Api\SharedKernel\XPM;

final readonly class Economy
{
    public function __construct(
        private GPM $gpm,
        private XPM $xpm,
        private NetWorth $netWorth,
        private GoldSpent $goldSpent,
    )
    {
    }

    public function getGpm(): GPM
    {
        return $this->gpm;
    }

    public function getXpm(): XPM
    {
        return $this->xpm;
    }

    public function getNetWorth(): NetWorth
    {
        return $this->netWorth;
    }

    public function getGoldSpent(): GoldSpent
    {
        return $this->goldSpent;
    }
}
