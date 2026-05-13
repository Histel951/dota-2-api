<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Domain\Entities\Match\ValueObjects;

use Histel951\Dota2Api\Domain\Common\ValueObjects\HeroId;
use Histel951\Dota2Api\Domain\Entities\Match\Enums\SideEnum;

final readonly class DraftDecision
{
    public function __construct(
        private HeroId $heroId,
        private DraftOrder $draftOrder,
        private SideEnum $side,
        private bool $isPick,
    )
    {
    }

    public function getHeroId(): HeroId
    {
        return $this->heroId;
    }

    public function isRadiant(): bool
    {
        return $this->side === SideEnum::RADIANT;
    }

    public function isDire(): bool
    {
        return $this->side === SideEnum::DIRE;
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