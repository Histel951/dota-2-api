<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Domain\Entities\Match\Services;

use Histel951\Dota2Api\Domain\Common\Enums\PlayerRole;
use Histel951\Dota2Api\Domain\Common\ValueObjects\Role;
use Histel951\Dota2Api\Domain\Entities\Match\Enums\Lane;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\MatchPlayerLane;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\MatchPlayerPerformance;
use Histel951\Dota2Api\Domain\Services\RoleResolverInterface;

class ProSceneRoleResolver implements RoleResolverInterface
{
    /**
     * @param MatchPlayerPerformance[] $players
     * @param bool $isRadiant
     * @return MatchPlayerPerformance[]
     */
    public function resolve(array $players, bool $isRadiant = true): array
    {
        $resolved = [];
        $safeLane = [];
        $offLane = [];
        /** @var MatchPlayerPerformance|null $midLane */
        $midLane = null;

        foreach ($players as $player) {
            $lane = $this->normalizePlayerLane(
                playerLane: $player->getIdentity()->getLane(),
                isRadiant: $isRadiant
            );

            match ($lane) {
                Lane::SAFE => $safeLane[] = $player,
                Lane::OFFLANE => $offLane[] = $player,
                Lane::MIDDLE => $midLane = $player,
            };
        }

        if (null !== $midLane) {
            $resolved[] = $midLane->withRole(
                new Role(PlayerRole::MIDDLE)
            );
        }

        return array_merge(
            $resolved,
            $this->assignCoreSupportRoles($safeLane, PlayerRole::CARRY, PlayerRole::HARD_SUPPORT),
            $this->assignCoreSupportRoles($offLane, PlayerRole::OFFLANE, PlayerRole::SUPPORT)
        );
    }

    /**
     * Нормализует линию игрока если он играет за dire, тк статистика привязана к карте, а не стороне
     *
     * @param MatchPlayerLane $playerLane
     * @param bool $isRadiant
     * @return Lane
     */
    private function normalizePlayerLane(MatchPlayerLane $playerLane, bool $isRadiant): Lane
    {
        $lane = $playerLane->getValue();

        if (!$isRadiant) {
            $lane = match ($lane) {
                Lane::SAFE => Lane::OFFLANE,
                Lane::OFFLANE => Lane::SAFE,
                default => $lane,
            };
        }

        return $lane;
    }

    /**
     *  Больше GPM = core
     *  меньше GPM = support
     *
     * @param array $players
     * @param PlayerRole $core
     * @param PlayerRole $support
     * @return array
     */
    private function assignCoreSupportRoles(array $players, PlayerRole $core, PlayerRole $support): array
    {
        if (count($players) !== 2) {
            return [];
        }

        usort(
            $players,
            fn (MatchPlayerPerformance $a, MatchPlayerPerformance $b)
            => $b->getEconomy()->getGPM()->getValue()
                <=> $a->getEconomy()->getGPM()->getValue()
        );

        return [
            $players[0]->withRole(new Role($core)),
            $players[1]->withRole(new Role($support)),
        ];
    }
}