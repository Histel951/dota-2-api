<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Domain\Entities\Match\ValueObjects;

final readonly class Draft
{
    /**
     * @param DraftDecision[] $picks
     * @param DraftDecision[] $bans
     */
    public function __construct(
        private array $picks,
        private array $bans,
    )
    {
    }

    /**
     * @return DraftDecision[]
     */
    public function getPicks(): array
    {
        return $this->picks;
    }

    /**
     * @return DraftDecision[]
     */
    public function getBans(): array
    {
        return $this->bans;
    }
}