<?php
declare(strict_types = 1);

namespace Histel951\Dota2Dota\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    function getConfigTreeBuilder(): TreeBuilder
    {
        $tree = new TreeBuilder('dota2_api');

        $rootNode = $tree->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('base_opendota_url')
                ->defaultValue('https://api.opendota.com/api')
                ->info('URL for dota 2 api.')
            ->end()

            ->scalarNode('base_stratz_url')
                ->defaultValue('https://api.stratz.com/')
                ->info('URL for dota 2 api.')
            ->end()

            ->scalarNode('opendota_api_key')
                ->info('Opendota API key for dota 2 api.')
            ->end()

            ->scalarNode('stratz_api_key')
                ->info('Stratz API key for dota 2 api.')
            ->end()

            ->scalarNode('timeout')
                ->defaultValue(120)
                ->info('Timeout for dota 2 api.')
            ->end();

        return $tree;
    }
}