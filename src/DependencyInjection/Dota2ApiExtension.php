<?php
declare(strict_types=1);

namespace Histel951\Dota2Api\DependencyInjection;

use Histel951\Dota2Api\Infrastructure\Http\Enums\ApiSource;
use Histel951\Dota2Api\Infrastructure\OpenDota\Http\OpenDotaHttpClientFactory;
use Histel951\Dota2Api\Infrastructure\OpenDota\Provider\OpenDotaProviderFactory;
use Histel951\Dota2Api\Infrastructure\Provider\Contracts\ProviderFactory;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
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

        $source = ApiSource::from($config['source']);

        $container->setParameter('dota2_api.timeout', $config['timeout']);
        $container->setParameter('dota2_api.max_retries', $config['max_retries']);
        $container->setParameter('dota2_api.source', $source);

        $loader = new PhpFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../Resources/config')
        );

        $loader->load('services.php');

        match ($source) {
            ApiSource::OPENDOTA => $this->loadOpenDota($config, $container),
            ApiSource::STRATZ => throw new InvalidConfigurationException(
                'dota2_api.source "stratz" is not implemented yet, use "opendota".'
            ),
        };
    }

    private function loadOpenDota(array $config, ContainerBuilder $container): void
    {
        // корневые base_url и api_key — устаревшие алиасы секции opendota
        $container->setParameter('dota2_api.base_url', $config['base_url'] ?? $config['opendota']['base_url']);
        $container->setParameter('dota2_api.api_key', $config['api_key'] ?? $config['opendota']['api_key']);

        $container->getDefinition('dota2_api.http_client')
            ->setFactory([OpenDotaHttpClientFactory::class, 'create']);

        $container->autowire(ProviderFactory::class, OpenDotaProviderFactory::class);
    }
}
