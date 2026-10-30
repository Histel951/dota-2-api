<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Stratz\Match\Domain;

use Histel951\Dota2Api\Stratz\Match\Domain\Exception\InvalidMatchSideException;
use Histel951\Dota2Api\Stratz\Match\Domain\Player\MatchPlayer;
use Histel951\Dota2Api\SharedKernel\Side;
use Histel951\Dota2Api\SharedKernel\TeamId;
use Histel951\Dota2Api\SharedKernel\TeamName;

final readonly class TeamSide
{
    /**
     * @param list<MatchPlayer> $players
     *
     * @throws InvalidMatchSideException игрок другой стороны
     */
    public function __construct(
        private Side $side,
        private TeamId $teamId,
        private TeamName $teamName,
        private array $players,
    )
    {
        foreach ($players as $player) {
            if ($player->getIdentity()->getSide() !== $side) {
                throw new InvalidMatchSideException(
                    sprintf('Player of side %s cannot belong to side %s', $player->getIdentity()->getSide()->name, $side->name)
                );
            }
        }
    }

    public function getSide(): Side
    {
        return $this->side;
    }

    public function getTeamId(): TeamId
    {
        return $this->teamId;
    }

    public function getTeamName(): TeamName
    {
        return $this->teamName;
    }

    /**
     * @return list<MatchPlayer>
     */
    public function getPlayers(): array
    {
        return $this->players;
    }

    public function isRadiant(): bool
    {
        return $this->side === Side::RADIANT;
    }
}
