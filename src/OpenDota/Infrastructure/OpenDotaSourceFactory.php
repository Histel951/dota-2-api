<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\OpenDota\Infrastructure;

use Histel951\Dota2Api\OpenDota\Infrastructure\Match\MatchExtractor;
use Histel951\Dota2Api\OpenDota\Infrastructure\Match\MatchMapper;
use Histel951\Dota2Api\OpenDota\Infrastructure\Match\OpenDotaMatchSource;
use Histel951\Dota2Api\OpenDota\Infrastructure\Team\OpenDotaTeamSource;
use Histel951\Dota2Api\OpenDota\Infrastructure\Team\TeamHeroMapper;
use Histel951\Dota2Api\OpenDota\Infrastructure\Team\TeamMapper;
use Histel951\Dota2Api\OpenDota\Infrastructure\Team\TeamMatchMapper;
use Histel951\Dota2Api\OpenDota\Infrastructure\Team\TeamPlayerMapper;
use Histel951\Dota2Api\OpenDota\Match\Port\MatchSourceInterface;
use Histel951\Dota2Api\OpenDota\Team\Port\TeamSourceInterface;

final readonly class OpenDotaSourceFactory
{
    public function __construct(
        private ApiGatewayInterface $gateway,
    )
    {
    }

    public function createMatchSource(): MatchSourceInterface
    {
        return new OpenDotaMatchSource(
            gateway: $this->gateway,
            extractor: new MatchExtractor(),
            mapper: new MatchMapper(),
        );
    }

    public function createTeamSource(): TeamSourceInterface
    {
        return new OpenDotaTeamSource(
            gateway: $this->gateway,
            teamMapper: new TeamMapper(),
            matchesMapper: new TeamMatchMapper(),
            playerMapper: new TeamPlayerMapper(),
            heroMapper: new TeamHeroMapper(),
        );
    }
}
