<?php
declare(strict_types=1);

namespace Histel951\Dota2Api\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

final class Dota2ApiExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter('dota2_api.timeout', $config['timeout']);
        $container->setParameter('dota2_api.max_retries', $config['max_retries']);
        $container->setParameter('dota2_api.opendota.base_url', $config['opendota']['base_url']);
        $container->setParameter('dota2_api.opendota.api_key', $config['opendota']['api_key']);
        $container->setParameter('dota2_api.stratz.base_url', $config['stratz']['base_url']);
        $container->setParameter('dota2_api.stratz.token', $config['stratz']['token']);
        $container->setParameter('dota2_api.stratz.rate_limit', $config['stratz']['rate_limit']);

        $loader = new PhpFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../Resources/config')
        );

        $loader->load('services.php');
        $loader->load('opendota.php');
        $loader->load('stratz.php');
    }
}
