<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Infrastructure\OpenDota\Provider;

use Histel951\Dota2Api\Domain\Entities\Match\Services\ProSceneRoleResolver;
use Histel951\Dota2Api\Domain\Providers\MatchesProviderInterface;
use Histel951\Dota2Api\Domain\Providers\TeamProviderInterface;
use Histel951\Dota2Api\Domain\Services\RoleResolverInterface;
use Histel951\Dota2Api\Infrastructure\Http\Contracts\ApiGatewayInterface;
use Histel951\Dota2Api\Infrastructure\OpenDota\Extractor\MatchExtractor;
use Histel951\Dota2Api\Infrastructure\OpenDota\Mapper\MatchOpenDotaMapper;
use Histel951\Dota2Api\Infrastructure\OpenDota\Mapper\TeamHeroOpenDotaMapper;
use Histel951\Dota2Api\Infrastructure\OpenDota\Mapper\TeamMatchOpenDotaMapper;
use Histel951\Dota2Api\Infrastructure\OpenDota\Mapper\TeamOpenDotaMapper;
use Histel951\Dota2Api\Infrastructure\OpenDota\Mapper\TeamPlayerOpenDotaMapper;
use Histel951\Dota2Api\Infrastructure\Provider\Contracts\ProviderFactory;

final class OpenDotaProviderFactory implements ProviderFactory
{
    private readonly RoleResolverInterface $roleResolver;

    public function __construct(
        private readonly ApiGatewayInterface $gateway,
        ?RoleResolverInterface $roleResolver = null,
    )
    {
        $this->roleResolver = $roleResolver ?? new ProSceneRoleResolver();
    }

    public function createMatchProvider(): MatchesProviderInterface
    {
        return new OpenDotaMatchProvider(
            gateway: $this->gateway,
            mapper: new MatchOpenDotaMapper($this->roleResolver),
            extractor: new MatchExtractor(),
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