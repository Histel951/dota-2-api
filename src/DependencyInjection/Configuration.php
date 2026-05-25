<?php
declare(strict_types=1);

namespace Histel951\Dota2Api\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('dota2_api');

        $treeBuilder->getRootNode()
            ->children()
            ->scalarNode('base_url')
            ->defaultValue('https://api.opendota.com/api/')
            ->end()

            ->scalarNode('api_key')
            ->defaultNull()
            ->end()

            ->integerNode('timeout')
            ->defaultValue(30)
            ->end()

            ->scalarNode('source')
            ->defaultValue('opendota')
            ->end()
            ->end();

        return $treeBuilder;
    }
}