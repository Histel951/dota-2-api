<?php

declare(strict_types=1);

namespace Histel951\Dota2Api\Domain\Entities\Match\Services;

use Histel951\Dota2Api\Domain\Common\Enums\PlayerRole;
use Histel951\Dota2Api\Domain\Common\Exceptions\DomainException;
use Histel951\Dota2Api\Domain\Common\ValueObjects\Role;
use Histel951\Dota2Api\Domain\Entities\Match\Enums\Lane;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\MatchPlayerLane;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\MatchPlayerPerformance;
use Histel951\Dota2Api\Domain\Services\RoleResolverInterface;

final class ProSceneRoleResolver implements RoleResolverInterface
{
    /**
     * @param MatchPlayerPerformance[] $players
     * @return MatchPlayerPerformance[]
     * @throws DomainException
     */
    public function resolve(array $players, bool $isRadiant = true): array
    {
        $lanes = [
            Lane::SAFE->value => [],
            Lane::MIDDLE->value => [],
            Lane::OFFLANE->value => [],
        ];

        foreach ($players as $player) {
            $lane = $this->normalizePlayerLane(
                $player->getIdentity()->getLane(),
                $isRadiant
            );

            $lanes[$lane->value][] = $player;
        }

        $this->assertLaneComposition($lanes);

        return [
            $lanes[Lane::MIDDLE->value][0]->withRole(
                new Role(PlayerRole::MIDDLE)
            ),

            ...$this->assignCoreSupportRoles(
                $lanes[Lane::SAFE->value],
                PlayerRole::CARRY,
                PlayerRole::HARD_SUPPORT
            ),

            ...$this->assignCoreSupportRoles(
                $lanes[Lane::OFFLANE->value],
                PlayerRole::OFFLANE,
                PlayerRole::SUPPORT
            ),
        ];
    }

    private function normalizePlayerLane(
        MatchPlayerLane $playerLane,
        bool $isRadiant
    ): Lane {
        if ($isRadiant) {
            return $playerLane->getValue();
        }

        return match ($playerLane->getValue()) {
            Lane::SAFE => Lane::OFFLANE,
            Lane::OFFLANE => Lane::SAFE,
            default => $playerLane->getValue(),
        };
    }

    /**
     * @param array<string, MatchPlayerPerformance[]> $lanes
     *
     * @throws DomainException
     */
    private function assertLaneComposition(array $lanes): void
    {
        if (count($lanes[Lane::MIDDLE->value]) !== 1) {
            throw new DomainException(
                sprintf(
                    'Expected exactly 1 mid player, got %d.',
                    count($lanes[Lane::MIDDLE->value])
                )
            );
        }

        if (count($lanes[Lane::SAFE->value]) !== 2) {
            throw new DomainException(
                sprintf(
                    'Expected exactly 2 safe lane players, got %d.',
                    count($lanes[Lane::SAFE->value])
                )
            );
        }

        if (count($lanes[Lane::OFFLANE->value]) !== 2) {
            throw new DomainException(
                sprintf(
                    'Expected exactly 2 off lane players, got %d.',
                    count($lanes[Lane::OFFLANE->value])
                )
            );
        }
    }

    /**
     * @param MatchPlayerPerformance[] $players
     * @return MatchPlayerPerformance[]
     */
    private function assignCoreSupportRoles(
        array $players,
        PlayerRole $core,
        PlayerRole $support
    ): array {
        usort(
            $players,
            static fn (MatchPlayerPerformance $a, MatchPlayerPerformance $b): int =>
                $b->getEconomy()->getGPM()->getValue()
                <=> $a->getEconomy()->getGPM()->getValue()
        );

        return [
            $players[0]->withRole(new Role($core)),
            $players[1]->withRole(new Role($support)),
        ];
    }
}