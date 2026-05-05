<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Domain\Common\ValueObjects;

use Histel951\Dota2Api\Domain\Common\Exceptions\InvalidPlayerIdException;

final readonly class PlayerId
{
    // Steam ID32 находится в диапазоне от 1 до 4,294,967,295
    private const int MIN_STEAM_ID = 1;
    private const int MAX_STEAM_ID = 4294967295;

    // минимальный Steam ID64 (для конвертации)
    private const int STEAM_ID64_OFFSET = 76561197960265728;

    private string $value;
    private int $intValue;

    /**
     * @throws InvalidPlayerIdException
     */
    public function __construct(string|int $value)
    {
        $normalized = (string) $value;

        $this->validate($normalized);

        $this->value = ltrim($normalized, '0');
        $this->intValue = (int) $this->value;
    }

    /**
     * @throws InvalidPlayerIdException
     */
    private function validate(string $value): void
    {
        if ($value === '') {
            throw new InvalidPlayerIdException('Player ID cannot be empty');
        }

        if (!ctype_digit($value)) {
            throw new InvalidPlayerIdException(
                sprintf('Player ID must be numeric, "%s" given', $value)
            );
        }

        $intValue = (int) $value;

        if ($intValue < self::MIN_STEAM_ID) {
            throw new InvalidPlayerIdException(
                sprintf('Player ID must be at least %d', self::MIN_STEAM_ID)
            );
        }

        if ($intValue > self::MAX_STEAM_ID) {
            throw new InvalidPlayerIdException(
                sprintf('Player ID cannot exceed %d', self::MAX_STEAM_ID)
            );
        }
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function toInt(): int
    {
        return $this->intValue;
    }

    /**
     * Конвертирует Steam ID32 в Steam ID64
     * Пример: 317880638 -> 76561197996545123
     */
    public function toSteamId64(): string
    {
        $steamId64 = $this->intValue + self::STEAM_ID64_OFFSET;
        return (string) $steamId64;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}