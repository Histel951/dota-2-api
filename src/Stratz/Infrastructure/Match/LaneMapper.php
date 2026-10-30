<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Stratz\Infrastructure\Match;

use Histel951\Dota2Api\Stratz\Match\Domain\Player\Lane;

/**
 * Переводит линию STRATZ (MatchLaneType, относительно команды) в Lane
 */
final class LaneMapper
{
    public function toLane(?string $lane): Lane
    {
        return match ($lane) {
            'SAFE_LANE' => Lane::SAFE,
            'MID_LANE' => Lane::MIDDLE,
            'OFF_LANE' => Lane::OFF,
            'JUNGLE' => Lane::JUNGLE,
            'ROAMING' => Lane::ROAMING,
            default => Lane::UNKNOWN,
        };
    }
}
