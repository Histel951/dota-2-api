<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Domain\Entities\Match\Services;

use Histel951\Dota2Api\Domain\Common\Enums\PlayerRole;
use Histel951\Dota2Api\SharedKernel\Exception\DomainException;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\MatchPlayerPerformance;
use Histel951\Dota2Api\Domain\Services\RoleResolverInterface;

/**
 * Оставляет роли, уже проставленные источником, если они образуют полный набор 1-5.
 * Иначе делегирует определение вложенному резолверу (на всю сторону, без смешивания).
 */
final readonly class PrefilledRoleResolver implements RoleResolverInterface
{
    private const int TEAM_SIZE = 5;

    public function __construct(private RoleResolverInterface $inner)
    {
    }

    /**
     * @param MatchPlayerPerformance[] $players
     * @return MatchPlayerPerformance[]
     * @throws DomainException
     */
    public function resolve(array $players, bool $isRadiant = true): array
    {
        if ($this->hasCompleteRoleSet($players)) {
            return $players;
        }

        return $this->inner->resolve($players, $isRadiant);
    }

    /**
     * @param MatchPlayerPerformance[] $players
     */
    private function hasCompleteRoleSet(array $players): bool
    {
        if (count($players) !== self::TEAM_SIZE) {
            return false;
        }

        $roles = [];
        foreach ($players as $player) {
            $role = $player->getRole()->getValue();
            if (PlayerRole::UNKNOWN === $role) {
                return false;
            }
            $roles[$role->value] = true;
        }

        return count($roles) === self::TEAM_SIZE;
    }
}
