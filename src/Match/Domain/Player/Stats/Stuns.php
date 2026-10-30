<?php
declare(strict_types=1);

namespace Histel951\Dota2Api\Match\Domain\Player\Stats;

final readonly class Stuns
{
    public function __construct(
        private float $value
    ) {}

    public function getValue(): float
    {
        return $this->value;
    }
}