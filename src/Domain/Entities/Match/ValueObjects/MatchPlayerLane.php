<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Domain\Entities\Match\ValueObjects;

use Histel951\Dota2Api\Domain\Entities\Match\Enums\LaneEnum;

final readonly class MatchPlayerLane
{
    public function __construct(
        private LaneEnum $value,
    )
    {
    }

    public function getValue(): LaneEnum
    {
        return $this->value;
    }
}