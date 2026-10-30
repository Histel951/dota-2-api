<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Infrastructure\OpenDota\Provider;

use Histel951\Dota2Api\Domain\Entities\Match\Services\PrefilledRoleResolver;
use Histel951\Dota2Api\Domain\Entities\Match\Services\ProSceneRoleResolver;
use Histel951\Dota2Api\Domain\Providers\MatchesProviderInterface;
use Histel951\Dota2Api\Team\Port\TeamSourceInterface;
use Histel951\Dota2Api\Domain\Services\RoleResolverInterface;
use Histel951\Dota2Api\Infrastructure\Http\ApiGatewayInterface;
use Histel951\Dota2Api\Infrastructure\OpenDota\Extractor\MatchExtractor;
use Histel951\Dota2Api\Infrastructure\OpenDota\Mapper\MatchOpenDotaMapper;
use Histel951\Dota2Api\Infrastructure\OpenDota\Team\OpenDotaTeamSource;
use Histel951\Dota2Api\Infrastructure\OpenDota\Team\TeamHeroMapper;
use Histel951\Dota2Api\Infrastructure\OpenDota\Team\TeamMatchMapper;
use Histel951\Dota2Api\Infrastructure\OpenDota\Team\TeamMapper;
use Histel951\Dota2Api\Infrastructure\OpenDota\Team\TeamPlayerMapper;
use Histel951\Dota2Api\Infrastructure\Provider\Contracts\ProviderFactory;

final class OpenDotaProviderFactory implements ProviderFactory
{
    private readonly RoleResolverInterface $roleResolver;

    public function __construct(
        private readonly ApiGatewayInterface $gateway,
        ?RoleResolverInterface $roleResolver = null,
    )
    {
        $this->roleResolver = $roleResolver ?? new PrefilledRoleResolver(inner: new ProSceneRoleResolver());
    }

    public function createMatchProvider(): MatchesProviderInterface
    {
        return new OpenDotaMatchProvider(
            gateway: $this->gateway,
            mapper: new MatchOpenDotaMapper($this->roleResolver),
            extractor: new MatchExtractor(),
        );
    }

    public function createTeamProvider(): TeamSourceInterface
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