<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Stratz\Infrastructure;

use Histel951\Dota2Api\Stratz\Infrastructure\Match\MatchMapper;
use Histel951\Dota2Api\Stratz\Infrastructure\Match\StratzMatchSource;
use Histel951\Dota2Api\Stratz\Infrastructure\Team\StratzTeamSource;
use Histel951\Dota2Api\Stratz\Infrastructure\Team\TeamHeroMapper;
use Histel951\Dota2Api\Stratz\Infrastructure\Team\TeamMapper;
use Histel951\Dota2Api\Stratz\Infrastructure\Team\TeamMatchMapper;
use Histel951\Dota2Api\Stratz\Infrastructure\Team\TeamPlayerMapper;
use Histel951\Dota2Api\Stratz\Match\Port\MatchSourceInterface;
use Histel951\Dota2Api\Stratz\Team\Port\TeamSourceInterface;

final readonly class StratzSourceFactory
{
    public function __construct(
        private GraphQlGateway $gateway,
    )
    {
    }

    public function createMatchSource(): MatchSourceInterface
    {
        return new StratzMatchSource(
            gateway: $this->gateway,
            mapper: new MatchMapper(),
        );
    }

    public function createTeamSource(): TeamSourceInterface
    {
        return new StratzTeamSource(
            gateway: $this->gateway,
            teamMapper: new TeamMapper(),
            matchesMapper: new TeamMatchMapper(),
            playerMapper: new TeamPlayerMapper(),
            heroMapper: new TeamHeroMapper(),
        );
    }
}
