<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\DependencyInjection;

use Histel951\Dota2Dota\DependencyInjection\Configuration;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class Dota2ApiExtension extends Extension
{

    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('dota2_api.base_opendota_url', $config['base_opendota_url']);
        $container->setParameter('dota2_api.base_stratz_url', $config['base_stratz_url']);
        $container->setParameter('dota2_api.timeout', $config['timeout']);
        $container->setParameter('dota2_api.stratz_api_key', $config['stratz_api_key']);
        $container->setParameter('dota2_api.opendota_api_key', $config['opendota_api_key']);

        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../../config')
        );

        $loader->load('services.yaml');
    }
}