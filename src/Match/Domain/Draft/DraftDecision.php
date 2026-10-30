<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Match\Domain\Draft;

use Histel951\Dota2Api\Match\Domain\Side\Side;
use Histel951\Dota2Api\SharedKernel\HeroId;

final readonly class DraftDecision
{
    public function __construct(
        private HeroId     $heroId,
        private DraftOrder $draftOrder,
        private Side       $side,
        private bool       $isPick,
    )
    {
    }

    public function getHeroId(): HeroId
    {
        return $this->heroId;
    }

    public function isRadiant(): bool
    {
        return $this->side === Side::RADIANT;
    }

    public function isDire(): bool
    {
        return $this->side === Side::DIRE;
    }

    public function getDraftOrder(): DraftOrder
    {
        return $this->draftOrder;
    }

    public function isPick(): bool
    {
        return $this->isPick;
    }
}