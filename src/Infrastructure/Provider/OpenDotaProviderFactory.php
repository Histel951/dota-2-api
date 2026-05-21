<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Infrastructure\Provider;

use Histel951\Dota2Api\Domain\Providers\MatchesProviderInterface;
use Histel951\Dota2Api\Domain\Providers\TeamProviderInterface;
use Histel951\Dota2Api\Infrastructure\Http\Contracts\ApiGatewayInterface;
use Histel951\Dota2Api\Infrastructure\Mapper\Match\MatchOpenDotaMapper;
use Histel951\Dota2Api\Infrastructure\Mapper\Team\TeamHeroOpenDotaMapper;
use Histel951\Dota2Api\Infrastructure\Mapper\Team\TeamMatchOpenDotaMapper;
use Histel951\Dota2Api\Infrastructure\Mapper\Team\TeamOpenDotaMapper;
use Histel951\Dota2Api\Infrastructure\Mapper\Team\TeamPlayerOpenDotaMapper;
use Histel951\Dota2Api\Infrastructure\Provider\Contracts\ProviderFactory;
use Histel951\Dota2Api\Infrastructure\Services\ProSceneRoleResolver;

final class OpenDotaProviderFactory implements ProviderFactory
{
    public function __construct(
        private readonly ApiGatewayInterface $gateway,
    )
    {
    }

    public function createMatchProvider(): MatchesProviderInterface
    {
        return new OpenDotaMatchProvider(
            gateway: $this->gateway,
            mapper: new MatchOpenDotaMapper(new ProSceneRoleResolver())
        );
    }

    public function createTeamProvider(): TeamProviderInterface
    {
        return new OpenDotaTeamProvider(
            gateway: $this->gateway,
            teamMapper: new TeamOpenDotaMapper(),
            matchesMapper: new TeamMatchOpenDotaMapper(),
            playerMapper: new TeamPlayerOpenDotaMapper(),
            heroMapper: new TeamHeroOpenDotaMapper(),
        );
    }
}