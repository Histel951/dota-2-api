<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\OpenDota\Match\Domain\Player;

final readonly class MatchPlayerLane
{
    public function __construct(
        private Lane $value,
    )
    {
    }

    public function getValue(): Lane
    {
        return $this->value;
    }
}