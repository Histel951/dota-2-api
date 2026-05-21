<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Domain\Common\ValueObjects;

use Histel951\Dota2Api\Domain\Common\Exceptions\InvalidCounterException;

abstract readonly class Counter
{
    /**
     * @throws InvalidCounterException
     */
    public function __construct(
        protected int $value
    ) {
        if ($value < 0) {
            throw new InvalidCounterException('Counter cannot be negative');
        }
    }

    public function getValue(): int
    {
        return $this->value;
    }
}