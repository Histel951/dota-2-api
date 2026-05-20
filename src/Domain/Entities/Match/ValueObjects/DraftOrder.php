<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Domain\Entities\Match\ValueObjects;

use Histel951\Dota2Api\Domain\Entities\Match\Exceptions\InvalidDraftOrderException;

final readonly class DraftOrder
{
    /**
     * @throws InvalidDraftOrderException
     */
    public function __construct(
        private int $value,
    )
    {
        if ($value < 0) {
            throw new InvalidDraftOrderException('Draft order cannot be less than zero');
        }

        if ($value > 23) {
            throw new InvalidDraftOrderException('Draft order cannot be greater than 23');
        }
    }

    public function getValue(): int
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return (string)$this->value;
    }
}