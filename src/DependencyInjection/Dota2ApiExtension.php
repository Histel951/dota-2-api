<?php
declare(strict_types=1);

namespace Histel951\Dota2Api\DependencyInjection;

use Histel951\Dota2Api\Domain\Entities\Match\Services\ProSceneRoleResolver;
use Histel951\Dota2Api\Domain\Providers\MatchesProviderInterface;
use Histel951\Dota2Api\Domain\Providers\TeamProviderInterface;
use Histel951\Dota2Api\Domain\Services\RoleResolverInterface;
use Histel951\Dota2Api\Infrastructure\Http\ApiGateway;
use Histel951\Dota2Api\Infrastructure\Http\Contracts\ApiGatewayInterface;
use Histel951\Dota2Api\Infrastructure\Http\Enums\ApiSource;
use Histel951\Dota2Api\Infrastructure\Mapper\Match\MatchOpenDotaMapper;
use Histel951\Dota2Api\Infrastructure\Mapper\Team\TeamHeroOpenDotaMapper;
use Histel951\Dota2Api\Infrastructure\Mapper\Team\TeamMatchOpenDotaMapper;
use Histel951\Dota2Api\Infrastructure\Mapper\Team\TeamOpenDotaMapper;
use Histel951\Dota2Api\Infrastructure\Mapper\Team\TeamPlayerOpenDotaMapper;
use Histel951\Dota2Api\Infrastructure\Provider\Contracts\ExtractorInterface;
use Histel951\Dota2Api\Infrastructure\Provider\Extractors\MatchExtractor;
use Histel951\Dota2Api\Infrastructure\Provider\OpenDotaMatchProvider;
use Histel951\Dota2Api\Infrastructure\Provider\OpenDotaTeamProvider;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

final class Dota2ApiExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();

        $config = $this->processConfiguration(
            $configuration,
            $configs
        );

        $container->setParameter(
            'dota2_api.base_url',
            $config['base_url']
        );

        $container->setParameter(
            'dota2_api.api_key',
            $config['api_key']
        );

        $container->setParameter(
            'dota2_api.timeout',
            $config['timeout']
        );

        $container->setParameter(
            'dota2_api.source',
            ApiSource::from($config['source'])
        );

        $loader = new PhpFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../Resources/config')
        );

        $loader->load('services.php');

        $container->autowire(
            ApiGatewayInterface::class,
            ApiGateway::class,
        );

        $container->autowire(
            ExtractorInterface::class,
            MatchExtractor::class,
        )->setPublic(true);

        $container->autowire(
            RoleResolverInterface::class,
            ProSceneRoleResolver::class
        )->setPublic(true);

        if (ApiSource::OPENDOTA->value === $config['source']) {
            $container->autowire(MatchOpenDotaMapper::class)->setPublic(true);
            $container->autowire(TeamMatchOpenDotaMapper::class)->setPublic(true);
            $container->autowire(TeamHeroOpenDotaMapper::class)->setPublic(true);
            $container->autowire(TeamOpenDotaMapper::class)->setPublic(true);
            $container->autowire(TeamPlayerOpenDotaMapper::class)->setPublic(true);

            $container->autowire(
                MatchesProviderInterface::class,
                OpenDotaMatchProvider::class,
            )->addTag('dota2_api.provider');

            $container->autowire(
                TeamProviderInterface::class,
                OpenDotaTeamProvider::class,
            )->addTag('dota2_api.provider');
        }
    }
}