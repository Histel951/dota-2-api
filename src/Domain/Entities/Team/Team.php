<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Domain\Entities\Team;

use DateTimeImmutable;
use Histel951\Dota2Api\Domain\Common\ValueObjects\TeamId;
use Histel951\Dota2Api\Domain\Common\ValueObjects\TeamName;
use Histel951\Dota2Api\Domain\Entities\Team\ValueObjects\TeamStats;
use Histel951\Dota2Api\Domain\Entities\Team\ValueObjects\TeamTag;

final class Team
{
    public function __construct(
        private readonly TeamId            $id,
        private readonly ?TeamName         $name,
        private readonly TeamTag           $tag,
        private readonly TeamStats         $stats,
        private readonly DateTimeImmutable $lastMatchTime,
    )
    {
    }

    public function getId(): TeamId
    {
        return $this->id;
    }

    public function getName(): ?TeamName
    {
        return $this->name;
    }

    public function getTag(): TeamTag
    {
        return $this->tag;
    }

    public function getStats(): TeamStats
    {
        return $this->stats;
    }

    public function getLastMatchTime(): DateTimeImmutable
    {
        return $this->lastMatchTime;
    }
}