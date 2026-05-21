<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Domain\Entities\Match\ValueObjects;

final readonly class WardingStats
{
    public function __construct(
        private ObserversPlaced $observersPlaced,
        private SentryPlaced $sentryPlaced,
        private ObserversDestroyed $observersDestroyed,
        private SentryDestroyed $sentryDestroyed,
    )
    {
    }

    public function getObserversPlaced(): ObserversPlaced
    {
        return $this->observersPlaced;
    }

    public function getSentryPlaced(): SentryPlaced
    {
        return $this->sentryPlaced;
    }

    public function getObserversDestroyed(): ObserversDestroyed
    {
        return $this->observersDestroyed;
    }

    public function getSentryDestroyed(): SentryDestroyed
    {
        return $this->sentryDestroyed;
    }
}