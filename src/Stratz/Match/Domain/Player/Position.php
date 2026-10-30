<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Stratz\Match\Domain\Player;

enum Position: int
{
    case CARRY = 1;
    case MIDDLE = 2;
    case OFFLANE = 3;
    case SOFT_SUPPORT = 4;
    case HARD_SUPPORT = 5;
}
