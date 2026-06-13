<?php
declare(strict_types=1);

namespace Histel951\Dota2Api\DependencyInjection;

use Histel951\Dota2Api\Domain\Providers\MatchesProviderInterface;
use Histel951\Dota2Api\Domain\Providers\TeamProviderInterface;
use Histel951\Dota2Api\Infrastructure\Http\Enums\ApiSource;
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

        if (ApiSource::OPENDOTA->value === $config['source']) {
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