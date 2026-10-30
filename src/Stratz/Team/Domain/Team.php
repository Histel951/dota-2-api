<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Stratz\Team\Domain;

use DateTimeImmutable;
use Histel951\Dota2Api\SharedKernel\TeamId;
use Histel951\Dota2Api\SharedKernel\TeamName;
use Histel951\Dota2Api\SharedKernel\TeamTag;
use Histel951\Dota2Api\SharedKernel\WinrateStats;

final readonly class Team
{
    public function __construct(
        private TeamId            $id,
        private ?TeamName         $name,
        private ?TeamTag          $tag,
        private WinrateStats      $record,
        private DateTimeImmutable $lastMatchTime,
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

    public function getTag(): ?TeamTag
    {
        return $this->tag;
    }

    public function getRecord(): WinrateStats
    {
        return $this->record;
    }

    public function getLastMatchTime(): DateTimeImmutable
    {
        return $this->lastMatchTime;
    }
}
