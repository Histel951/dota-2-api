<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\OpenDota\Match\Domain\Player;

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

    public function getValue(): array
    {
        return $this->value;
    }
}
