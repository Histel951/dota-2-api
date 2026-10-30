<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\OpenDota\Infrastructure\Match;

use Histel951\Dota2Api\OpenDota\Match\Domain\Player\Lane;

/**
 * Приводит линию игрока из формата OpenDota к Lane со стороны radiant
 */
final class LaneNormalizer
{
    /**
     * OpenDota отдаёт:
     * - lane: абсолютная позиция на карте — 1 bot, 2 mid, 3 top, 4 лес radiant, 5 лес dire
     * - lane_role: линия относительно команды игрока — 1 safe, 2 mid, 3 offlane, 4 лес
     *
     * Bot/mid/top совпадают с Lane::SAFE/MIDDLE/OFFLANE со стороны radiant и берутся как есть.
     * Для леса линия восстанавливается по lane_role с переводом в сторону radiant.
     *
     * @param int|null $lane Абсолютная позиция на карте
     * @param int|null $laneRole Линия относительно команды игрока
     * @param bool $isRadiant Игрок за radiant
     */
    public function normalize(?int $lane, ?int $laneRole, bool $isRadiant): Lane
    {
        $resolved = Lane::tryFrom($lane ?? 0);

        if (null !== $resolved) {
            return $resolved;
        }

        return match ($laneRole) {
            2 => Lane::MIDDLE,
            3 => $isRadiant ? Lane::OFFLANE : Lane::SAFE,
            default => $isRadiant ? Lane::SAFE : Lane::OFFLANE,
        };
    }
}
