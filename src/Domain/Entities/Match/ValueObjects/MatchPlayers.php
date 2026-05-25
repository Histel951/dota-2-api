<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Domain\Entities\Match\ValueObjects;

use Histel951\Dota2Api\Domain\Common\Enums\PlayerRole;
use Histel951\Dota2Api\Domain\Entities\Match\Enums\Side;
use Histel951\Dota2Api\Domain\Entities\Match\Exceptions\MatchPlayersException;

final readonly class MatchPlayers
{
    /**
     * @param MatchPlayerPerformance[] $value
     */
    public function __construct(
        private array $value,
    )
    {
    }

    /**
     * @throws MatchPlayersException
     */
    public function getByRole(PlayerRole $role, Side $side): MatchPlayerPerformance
    {
        foreach ($this->value as $player) {
            if ($player->getRole()->getValue() === $role && $player->getIdentity()->getSide() === $side) {
                return $player;
            }
        }

        throw new MatchPlayersException('Player not found');
    }

    public function getValue(): array
    {
        return $this->value;
    }
}