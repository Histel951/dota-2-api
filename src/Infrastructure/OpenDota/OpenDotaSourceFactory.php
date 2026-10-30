<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Infrastructure\OpenDota;

use Histel951\Dota2Api\Infrastructure\Http\ApiGatewayInterface;
use Histel951\Dota2Api\Infrastructure\OpenDota\Match\MatchExtractor;
use Histel951\Dota2Api\Infrastructure\OpenDota\Match\MatchMapper;
use Histel951\Dota2Api\Infrastructure\OpenDota\Match\OpenDotaMatchSource;
use Histel951\Dota2Api\Infrastructure\OpenDota\Team\OpenDotaTeamSource;
use Histel951\Dota2Api\Infrastructure\OpenDota\Team\TeamHeroMapper;
use Histel951\Dota2Api\Infrastructure\OpenDota\Team\TeamMapper;
use Histel951\Dota2Api\Infrastructure\OpenDota\Team\TeamMatchMapper;
use Histel951\Dota2Api\Infrastructure\OpenDota\Team\TeamPlayerMapper;
use Histel951\Dota2Api\Infrastructure\SourceFactoryInterface;
use Histel951\Dota2Api\Match\Port\MatchSourceInterface;
use Histel951\Dota2Api\Team\Port\TeamSourceInterface;

final readonly class OpenDotaSourceFactory implements SourceFactoryInterface
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
