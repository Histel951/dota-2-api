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

        $middlePlayers = $this->resolveMiddlePlayers(
            $lanes[Lane::MIDDLE->value],
            $lanes[Lane::SAFE->value],
            $lanes[Lane::OFFLANE->value]
        );

        $safePlayers = $this->resolveSafeLanePlayers(
            $lanes[Lane::SAFE->value]
        );

        $offlanePlayers = $this->resolveOfflanePlayers(
            $lanes[Lane::OFFLANE->value]
        );

        return [
            ...$middlePlayers,
            ...$safePlayers,
            ...$offlanePlayers,
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
     * @throws DomainException
     */
    private function resolveSafeLanePlayers(array $players): array
    {
        return $this->resolveLanePlayers(
            $players,
            PlayerRole::CARRY,
            PlayerRole::HARD_SUPPORT,
            'safe'
        );
    }

    /**
     * @param MatchPlayerPerformance[] $players
     * @return MatchPlayerPerformance[]
     * @throws DomainException
     */
    private function resolveOfflanePlayers(array $players): array
    {
        return $this->resolveLanePlayers(
            $players,
            PlayerRole::OFFLANE,
            PlayerRole::SUPPORT,
            'offlane'
        );
    }

    /**
     * Обобщенный метод для обработки игроков на линии
     *
     * @param MatchPlayerPerformance[] $players
     * @param PlayerRole $coreRole Роль core игрока
     * @param PlayerRole $supportRole Роль саппорта (для случая с 2 игроками)
     * @param string $laneName Название линии для сообщений об ошибках
     * @return MatchPlayerPerformance[]
     * @throws DomainException
     */
    private function resolveLanePlayers(
        array $players,
        PlayerRole $coreRole,
        PlayerRole $supportRole,
        string $laneName
    ): array {
        $count = count($players);

        if ($count === 0) {
            return [];
        }

        if ($count === 1) {
            return [
                $players[0]->withRole(new Role($coreRole))
            ];
        }

        if ($count === 2) {
            return $this->assignCoreSupportRoles(
                $players,
                $coreRole,
                $supportRole
            );
        }

        if ($count >= 3) {
            return $this->assignCoreWithSupports($players, $coreRole);
        }

        throw new DomainException(
            sprintf(
                'Unexpected number of players on %s lane: %d.',
                $laneName,
                $count
            )
        );
    }

    /**
     * @param MatchPlayerPerformance[] $middlePlayers
     * @param MatchPlayerPerformance[] $safePlayers
     * @param MatchPlayerPerformance[] $offlanePlayers
     * @return MatchPlayerPerformance[]
     * @throws DomainException
     */
    private function resolveMiddlePlayers(
        array $middlePlayers,
        array $safePlayers,
        array $offlanePlayers
    ): array {
        $middleCount = count($middlePlayers);

        if ($middleCount === 1) {
            return [
                $middlePlayers[0]->withRole(new Role(PlayerRole::MIDDLE))
            ];
        }

        if ($middleCount === 2) {
            return $this->resolveTwoMiddlePlayers(
                $middlePlayers,
                $safePlayers,
                $offlanePlayers
            );
        }

        if ($middleCount === 3) {
            return $this->resolveThreeMiddlePlayers(
                $middlePlayers,
                $safePlayers,
                $offlanePlayers
            );
        }

        throw new DomainException(
            sprintf(
                'Unexpected number of players on middle lane: %d.',
                $middleCount
            )
        );
    }

    /**
     * @param MatchPlayerPerformance[] $middlePlayers
     * @param MatchPlayerPerformance[] $safePlayers
     * @param MatchPlayerPerformance[] $offlanePlayers
     * @return MatchPlayerPerformance[]
     * @throws DomainException
     */
    private function resolveTwoMiddlePlayers(
        array $middlePlayers,
        array $safePlayers,
        array $offlanePlayers
    ): array {
        $this->sortByGPM($middlePlayers);

        $hasSafeSupport = count($safePlayers) === 2;
        $hasOfflaneSupport = count($offlanePlayers) === 2;

        if (!$hasSafeSupport) {
            return [
                $middlePlayers[0]->withRole(new Role(PlayerRole::MIDDLE)),
                $middlePlayers[1]->withRole(new Role(PlayerRole::HARD_SUPPORT)),
            ];
        }

        if (!$hasOfflaneSupport) {
            return [
                $middlePlayers[0]->withRole(new Role(PlayerRole::MIDDLE)),
                $middlePlayers[1]->withRole(new Role(PlayerRole::SUPPORT)),
            ];
        }

        throw new DomainException(
            'Cannot resolve roles: two players on middle lane with normal lane composition.'
        );
    }

    /**
     * @param MatchPlayerPerformance[] $middlePlayers
     * @param MatchPlayerPerformance[] $safePlayers
     * @param MatchPlayerPerformance[] $offlanePlayers
     * @return MatchPlayerPerformance[]
     * @throws DomainException
     */
    private function resolveThreeMiddlePlayers(
        array $middlePlayers,
        array $safePlayers,
        array $offlanePlayers
    ): array {
        if (count($safePlayers) !== 1 || count($offlanePlayers) !== 1) {
            throw new DomainException(
                'Cannot resolve roles: three players on middle lane require exactly one player on each other lane.'
            );
        }

        $this->sortByGPM($middlePlayers);

        $realMiddle = array_shift($middlePlayers);
        $supports = $this->assignSupportsByWards($middlePlayers);

        return [
            $realMiddle->withRole(new Role(PlayerRole::MIDDLE)),
            ...$supports,
        ];
    }

    /**
     * Назначает роли для 3+ игроков на линии: core игрок и саппорты
     *
     * @param MatchPlayerPerformance[] $players
     * @param PlayerRole $coreRole
     * @return MatchPlayerPerformance[]
     */
    private function assignCoreWithSupports(array $players, PlayerRole $coreRole): array
    {
        $this->sortByGPM($players);

        $core = array_shift($players);
        $supports = $this->assignSupportsByWards($players);

        return [
            $core->withRole(new Role($coreRole)),
            ...$supports,
        ];
    }

    /**
     * Сортирует игроков по GPM (по убыванию)
     *
     * @param MatchPlayerPerformance[] $players
     * @return void
     */
    private function sortByGPM(array &$players): void
    {
        usort(
            $players,
            static fn (MatchPlayerPerformance $a, MatchPlayerPerformance $b): int =>
                $b->getEconomy()->getGPM()->getValue()
                <=> $a->getEconomy()->getGPM()->getValue()
        );
    }

    /**
     * Сортирует игроков по количеству sentry wards (по убыванию)
     *
     * @param MatchPlayerPerformance[] $players
     * @return void
     */
    private function sortBySentryWards(array &$players): void
    {
        usort(
            $players,
            static fn (MatchPlayerPerformance $a, MatchPlayerPerformance $b): int =>
                $b->getWarding()->getSentryPlaced()->getValue()
                <=> $a->getWarding()->getSentryPlaced()->getValue()
        );
    }

    /**
     * Назначает роли саппортов по количеству sentry wards
     *
     * @param MatchPlayerPerformance[] $players
     * @return MatchPlayerPerformance[]
     */
    private function assignSupportsByWards(array $players): array
    {
        $this->sortBySentryWards($players);

        return [
            $players[0]->withRole(new Role(PlayerRole::HARD_SUPPORT)),
            $players[1]->withRole(new Role(PlayerRole::SUPPORT)),
        ];
    }

    /**
     * Назначает роли для 2 игроков: core и саппорт по GPM
     *
     * @param MatchPlayerPerformance[] $players
     * @param PlayerRole $core
     * @param PlayerRole $support
     * @return MatchPlayerPerformance[]
     */
    private function assignCoreSupportRoles(
        array $players,
        PlayerRole $core,
        PlayerRole $support
    ): array {
        $this->sortByGPM($players);

        return [
            $players[0]->withRole(new Role($core)),
            $players[1]->withRole(new Role($support)),
        ];
    }
}