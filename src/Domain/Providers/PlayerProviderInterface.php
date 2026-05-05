<?php

namespace Histel951\Dota2Api\Domain\Providers;

interface PlayerProviderInterface
{
    public function getPlayer(int $playerId): array;
}