<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Domain\Common\ValueObjects;

final readonly class MatchOutcome
{
    private bool $isDire;
    private bool $isRadiant;
    private bool $isWon;
    private bool $isLoss;

    private function __construct(
        bool             $isRadiant,
        bool             $radiantWin,
        private int      $radiantScore,
        private int      $direScore,
        private Duration $duration,
    ) {
        $this->isRadiant = $isRadiant;
        $this->isDire = !$isRadiant;

        $this->isWon = ($isRadiant && $radiantWin) || (!$isRadiant && !$radiantWin);
        $this->isLoss = !$this->isWon;
    }

    public function isWon(): bool
    {
        return $this->isWon;
    }

    public function isLoss(): bool
    {
        return $this->isLoss;
    }

    public function isRadiant(): bool
    {
        return $this->isRadiant;
    }

    public function isDire(): bool
    {
        return $this->isDire;
    }

    public function getRadiantKills(): int
    {
        return $this->radiantScore;
    }

    public function getDireKills(): int
    {
        return $this->direScore;
    }

    public function getDuration(): Duration
    {
        return $this->duration;
    }

    public static function fromApi(
        bool $isRadiant,
        bool $radiantWon,
        int $radiantScore,
        int $direScore,
        Duration $duration
    ): self
    {
        return new self($isRadiant, $radiantWon, $radiantScore, $direScore, $duration);
    }
}