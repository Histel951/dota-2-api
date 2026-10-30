<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Match\Domain\Player;

enum Lane: int
{
    case SAFE = 1;
    case MIDDLE = 2;
    case OFFLANE = 3;
}