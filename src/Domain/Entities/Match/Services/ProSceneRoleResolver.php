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
    private const int TEAM_SIZE = 5;

    /**
     * @param MatchPlayerPerformance[] $players
     * @return MatchPlayerPerformance[]
     * @throws DomainException
     */
    public function resolve(array $players, bool $isRadiant = true): array
    {
        $playerCount = count($players);

        if ($playerCount !== self::TEAM_SIZE) {
            throw new DomainException(
                sprintf(
                    'Expected exactly %d players, got %d.',
                    self::TEAM_SIZE,
                    $playerCount
                )
            );
        }

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
        $lane = $playerLane->getValue();

        if ($isRadiant) {
            return $lane;
        }

        return match ($lane) {
            Lane::SAFE => Lane::OFFLANE,
            Lane::OFFLANE => Lane::SAFE,
            default => $lane,
        };
    }

    /**
     * @param MatchPlayerPerformance[] $players
     *
     * @throws DomainException
     */
    private function assertLaneCount(
        Lane $lane,
        array $players,
        int $expected
    ): void {
        $actual = count($players);

        if ($actual !== $expected) {
            throw new DomainException(
                sprintf(
                    'Expected exactly %d player(s) on %s lane, got %d.',
                    $expected,
                    strtolower($lane->name),
                    $actual,
                )
            );
        }
    }

    /**
     * @param array<string, MatchPlayerPerformance[]> $lanes
     *
     * @throws DomainException
     */
    private function assertLaneComposition(array $lanes): void
    {
        $this->assertLaneCount(
            Lane::MIDDLE,
            $lanes[Lane::MIDDLE->value],
            1
        );

        $this->assertLaneCount(
            Lane::SAFE,
            $lanes[Lane::SAFE->value],
            2
        );

        $this->assertLaneCount(
            Lane::OFFLANE,
            $lanes[Lane::OFFLANE->value],
            2
        );
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