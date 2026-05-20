<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Domain\Entities\Match\ValueObjects;

use Histel951\Dota2Api\Domain\Common\Enums\RoleEnum;
use Histel951\Dota2Api\Domain\Entities\Match\Exceptions\MatchPlayersException;

final readonly class MatchPlayers
{
    /**
     * @param MatchPlayerPerformance[] $players
     */
    public function __construct(
        private array $players,
    )
    {
    }

    /**
     * @throws MatchPlayersException
     */
    public function getByRole(RoleEnum $role, bool $isRadiant): MatchPlayerPerformance
    {
        foreach ($this->players as $player) {
            if ($player->getRole()->getValue() === $role && $player->isRadiant() === $isRadiant) {
                return $player;
            }
        }

        throw new MatchPlayersException('Player not found');
    }

    public function getPlayers(): array
    {
        return $this->players;
    }
}