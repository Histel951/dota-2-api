<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Stratz\Match\Domain\Player;

/**
 * Линия игрока относительно его команды
 */
enum Lane
{
    case SAFE;
    case MIDDLE;
    case OFF;
    case JUNGLE;
    case ROAMING;
    case UNKNOWN;
}
