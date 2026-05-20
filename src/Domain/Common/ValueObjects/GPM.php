<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Domain\Common\ValueObjects;

use Histel951\Dota2Api\Domain\Common\Exceptions\InvalidGPMException;

final readonly class GPM
{
    /**
     * @throws InvalidGPMException
     */
    public function __construct(
        private int $value,
    )
    {
        if ($value < 0) {
            throw new InvalidGPMException('GPM value must be greater than 0');
        }
    }

    public function getValue(): int
    {
        return $this->value;
    }
}