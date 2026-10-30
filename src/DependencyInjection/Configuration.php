<?php
declare(strict_types=1);

namespace Histel951\Dota2Api\DependencyInjection;

use Histel951\Dota2Api\OpenDota\Infrastructure\OpenDotaHttpClientFactory;
use Histel951\Dota2Api\Stratz\Infrastructure\StratzHttpClientFactory;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('dota2_api');

        $treeBuilder->getRootNode()
            ->children()
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
            ->defaultValue(OpenDotaHttpClientFactory::DEFAULT_BASE_URL)
            ->end()
            ->scalarNode('api_key')
            ->defaultNull()
            ->end()
            ->end()
            ->end()

            ->arrayNode('stratz')
            ->addDefaultsIfNotSet()
            ->children()
            ->scalarNode('base_url')
            ->defaultValue(StratzHttpClientFactory::DEFAULT_BASE_URL)
            ->end()
            ->scalarNode('token')
            ->defaultNull()
            ->end()
            ->integerNode('rate_limit')
            ->info('Лимит запросов к STRATZ в минуту, включая ретраи; null — без лимитера')
            ->min(1)
            ->defaultNull()
            ->end()
            ->end()
            ->end()
            ->end();

        return $treeBuilder;
    }
}
