<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Match\Domain\Side;

enum Side: int
{
    case RADIANT = 0;
    case DIRE = 1;
}