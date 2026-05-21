<?php

namespace Histel951\Dota2Api\Infrastructure\Services;

use Histel951\Dota2Api\Domain\Common\Enums\RoleEnum;
use Histel951\Dota2Api\Domain\Common\ValueObjects\Role;
use Histel951\Dota2Api\Domain\Entities\Match\Enums\Lane;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\MatchPlayerPerformance;
use Histel951\Dota2Api\Domain\Services\RoleResolverInterface;

class ProSceneRoleResolver implements RoleResolverInterface
{

    /**
     * @param MatchPlayerPerformance[] $players
     * @return MatchPlayerPerformance[]
     */
    public function resolve(array $players): array
    {
        $safeLane = [];
        $offLane = [];
        $midLane = [];

        foreach ($players as $player) {
            match ($player->getIdentity()->getLane()->getValue()) {
                Lane::SAFE => $safeLane[] = $player,
                Lane::OFFLANE => $offLane[] = $player,
                Lane::MIDDLE => $midLane[] = $player,
            };
        }

        $resolved = [];

        /**
         * MID = POS2
         */
        foreach ($midLane as $player) {
            $resolved[] = $player->withRole(
                new Role(RoleEnum::MIDDLE)
            );
        }

        /**
         * SAFE:
         * больше GPM = carry
         * меньше GPM = hard support
         */
        if (count($safeLane) === 2) {
            usort(
                $safeLane,
                fn(MatchPlayerPerformance $a, MatchPlayerPerformance $b)
                => $b->getEconomy()->getGPM()->getValue() <=> $a->getEconomy()->getGPM()->getValue()
            );

            $resolved[] = $safeLane[0]->withRole(
                new Role(RoleEnum::CARRY)
            );

            $resolved[] = $safeLane[1]->withRole(
                new Role(RoleEnum::HARD_SUPPORT)
            );
        }

        /**
         * OFFLANE:
         * больше GPM = offlane
         * меньше GPM = soft support
         */
        if (count($offLane) === 2) {
            usort(
                $offLane,
                fn(MatchPlayerPerformance $a, MatchPlayerPerformance $b)
                => $b->getEconomy()->getGPM()->getValue() <=> $a->getEconomy()->getGPM()->getValue()
            );

            $resolved[] = $offLane[0]->withRole(
                new Role(RoleEnum::OFFLANE)
            );

            $resolved[] = $offLane[1]->withRole(
                new Role(RoleEnum::SUPPORT)
            );
        }

        return $resolved;
    }
}