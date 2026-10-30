<?php
declare(strict_types=1);

namespace Histel951\Dota2Api\Infrastructure\Provider\Contracts;

use Histel951\Dota2Api\Domain\Providers\MatchesProviderInterface;
use Histel951\Dota2Api\Team\Port\TeamSourceInterface;

interface ProviderFactory
{
    public function createMatchProvider(): MatchesProviderInterface;

    public function createTeamProvider(): TeamSourceInterface;
}