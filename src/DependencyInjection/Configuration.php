<?php
declare(strict_types=1);

namespace Histel951\Dota2Api\DependencyInjection;

use Histel951\Dota2Api\Client\Config\ApiSource;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('dota2_api');

        $treeBuilder->getRootNode()
            ->children()
            ->enumNode('source')
            ->values(array_map(static fn (ApiSource $source): string => $source->value, ApiSource::cases()))
            ->defaultValue(ApiSource::OPENDOTA->value)
            ->end()

            ->integerNode('timeout')
            ->defaultValue(30)
            ->end()

            ->integerNode('max_retries')
            ->min(0)
            ->defaultValue(3)
            ->end()

            ->arrayNode('opendota')
            ->addDefaultsIfNotSet()
            ->children()
            ->scalarNode('base_url')
            ->defaultValue('https://api.opendota.com/api/')
            ->end()
            ->scalarNode('api_key')
            ->defaultNull()
            ->end()
            ->end()
            ->end()

            ->scalarNode('base_url')
            ->setDeprecated('histel951/dota-2-api', '1.1', 'The "%node%" option is deprecated, use "opendota.base_url" instead.')
            ->end()

            ->scalarNode('api_key')
            ->setDeprecated('histel951/dota-2-api', '1.1', 'The "%node%" option is deprecated, use "opendota.api_key" instead.')
            ->end()
            ->end();

        return $treeBuilder;
    }
}
