<?php
declare(strict_types=1);

namespace Histel951\Dota2Api\Domain\Entities\Match\ValueObjects;

final readonly class TeamfightParticipation
{
    private float $value;

    public function __construct(float $value)
    {
        $value = max(0.0, min(1.0, $value));
        $this->value = round($value, 2);
    }

    public function getValue(): float
    {
        return $this->value;
    }
}