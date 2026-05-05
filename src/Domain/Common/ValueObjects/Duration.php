<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Domain\Common\ValueObjects;

use Histel951\Dota2Api\Domain\Common\Exceptions\InvalidDurationException;

final readonly class Duration
{
    /**
     * @throws InvalidDurationException
     */
    private function __construct(
        private int $seconds
    ) {
        if ($seconds < 0) {
            throw new InvalidDurationException('Duration cannot be negative');
        }
    }

    /**
     * @throws InvalidDurationException
     */
    public static function fromSeconds(int $seconds): self
    {
        return new self($seconds);
    }

    /**
     * @throws InvalidDurationException
     */
    public static function fromMinutesAndSeconds(int $minutes, int $seconds): self
    {
        return new self($minutes * 60 + $seconds);
    }

    public function getSeconds(): int
    {
        return $this->seconds;
    }

    public function getMinutes(): int
    {
        return intdiv($this->seconds, 60);
    }

    public function getRemainingSeconds(): int
    {
        return $this->seconds % 60;
    }

    public function toMinutesWithSeconds(): string
    {
        $minutes = $this->getMinutes();
        $seconds = $this->getRemainingSeconds();

        return sprintf('%d:%02d', $minutes, $seconds);
    }

    public function toShortString(): string
    {
        $minutes = $this->getMinutes();
        $seconds = $this->getRemainingSeconds();

        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    public function equals(self $other): bool
    {
        return $this->seconds === $other->seconds;
    }
}