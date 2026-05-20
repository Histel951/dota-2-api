<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Domain\Common\Enums;

enum RoleEnum: int
{
    case UNKNOWN = 0;
    case CARRY = 1;
    case MIDDLE = 2;
    case OFFLANE = 3;
    case SUPPORT = 4;
    case HARD_SUPPORT = 5;
}