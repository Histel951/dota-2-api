<?php

namespace Histel951\Dota2Api\Infrastructure\Provider\Contracts;

use Histel951\Dota2Api\Domain\Providers\MatchesProviderInterface;
use Histel951\Dota2Api\Domain\Providers\TeamProviderInterface;

interface ProviderFactory
{
    public function createMatchProvider(): MatchesProviderInterface;

    public function createTeamProvider(): TeamProviderInterface;
}