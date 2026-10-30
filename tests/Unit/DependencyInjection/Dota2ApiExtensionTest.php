<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Tests\Unit\DependencyInjection;

use Histel951\Dota2Api\Client\Config\ClientConfig;
use Histel951\Dota2Api\Client\Dota2ApiClient;
use Histel951\Dota2Api\Stratz\Infrastructure\GraphQlGateway;
use Histel951\Dota2Api\Stratz\Infrastructure\Match\StratzMatchSource;
use Histel951\Dota2Api\Stratz\Infrastructure\StratzSourceFactory;
use Histel951\Dota2Api\Stratz\Infrastructure\Team\StratzTeamSource;
use Histel951\Dota2Api\Stratz\Match\Application\MatchService as StratzMatchService;
use Histel951\Dota2Api\Stratz\Match\Port\MatchSourceInterface as StratzMatchSourceInterface;
use Histel951\Dota2Api\Stratz\StratzClient;
use Histel951\Dota2Api\Stratz\Team\Port\TeamSourceInterface as StratzTeamSourceInterface;
use Histel951\Dota2Api\DependencyInjection\Dota2ApiExtension;
use Histel951\Dota2Api\OpenDota\Infrastructure\Match\OpenDotaMatchSource;
use Histel951\Dota2Api\OpenDota\Infrastructure\Team\OpenDotaTeamSource;
use Histel951\Dota2Api\OpenDota\Match\Application\MatchService;
use Histel951\Dota2Api\OpenDota\Match\Domain\Role\PrefilledRoleResolver;
use Histel951\Dota2Api\OpenDota\Match\Domain\Role\ProSceneRoleResolver;
use Histel951\Dota2Api\OpenDota\Match\Domain\Role\RoleResolverInterface;
use Histel951\Dota2Api\OpenDota\Match\Port\MatchSourceInterface;
use Histel951\Dota2Api\OpenDota\Team\Port\TeamSourceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class Dota2ApiExtensionTest extends TestCase
{
    public function test_clientIsBuiltFromContainer()
    {
        $container = $this->createContainer([['opendota' => ['api_key' => 'secret'], 'timeout' => 60]]);
        $container->compile();

        $client = $container->get(Dota2ApiClient::class);

        $this->assertInstanceOf(MatchService::class, $client->openDota()->matches());
        $this->assertInstanceOf(OpenDotaTeamSource::class, $client->openDota()->teams());
        $this->assertSame('secret', $container->getParameter('dota2_api.opendota.api_key'));
        $this->assertSame('https://api.opendota.com/api/', $container->getParameter('dota2_api.opendota.base_url'));
        $this->assertSame(60, $container->getParameter('dota2_api.timeout'));
        $this->assertSame(3, $container->getParameter('dota2_api.max_retries'));
    }

    public function test_sourceOptionIsRejected()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Unrecognized option');

        $this->createContainer([['source' => 'opendota']]);
    }

    public function test_legacyRootOptionsAreRejected()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Unrecognized option');

        $this->createContainer([['base_url' => 'https://api.opendota.com/api/', 'api_key' => 'legacy']]);
    }

    public function test_applicationHttpClientIsNotReplaced()
    {
        $container = $this->createContainer([[]]);
        $container->compile();

        $this->assertInstanceOf(MockHttpClient::class, $container->get(HttpClientInterface::class));
    }

    public function test_clientCanBeAutowiredByApplication()
    {
        // так клиент переопределяется в приложении-потребителе: autowire по OpenDotaClient
        $container = $this->createContainer([[]]);
        $container->register(Dota2ApiClient::class, Dota2ApiClient::class)
            ->setAutowired(true)
            ->setPublic(true);
        $container->compile();

        $this->assertInstanceOf(MatchService::class, $container->get(Dota2ApiClient::class)->openDota()->matches());
    }

    public function test_sourcesAreCreatedFromSourceFactory()
    {
        $container = $this->createContainer([[]]);
        foreach ([MatchSourceInterface::class, TeamSourceInterface::class, MatchService::class] as $id) {
            $container->getDefinition($id)->setPublic(true);
        }
        $container->compile();

        $this->assertInstanceOf(OpenDotaMatchSource::class, $container->get(MatchSourceInterface::class));
        $this->assertInstanceOf(OpenDotaTeamSource::class, $container->get(TeamSourceInterface::class));
        $this->assertInstanceOf(MatchService::class, $container->get(MatchService::class));
    }

    public function test_clientIsPublic()
    {
        $container = $this->createContainer([[]]);

        $this->assertTrue($container->getDefinition(Dota2ApiClient::class)->isPublic());
    }

    public function test_matchServiceReceivesPrefilledRoleResolver()
    {
        $container = $this->createContainer([[]]);
        $container->getDefinition(MatchService::class)->setPublic(true);
        $container->compile();

        $resolver = (new \ReflectionProperty(MatchService::class, 'roleResolver'))
            ->getValue($container->get(MatchService::class));

        $this->assertInstanceOf(PrefilledRoleResolver::class, $resolver);
    }

    public function test_roleResolverIsPrefilledDecoratorOverHeuristic()
    {
        $container = $this->createContainer([[]]);

        $definition = $container->getDefinition(RoleResolverInterface::class);
        $inner = $definition->getArgument('$inner');

        $this->assertSame(PrefilledRoleResolver::class, $definition->getClass());
        $this->assertInstanceOf(Definition::class, $inner);
        $this->assertSame(ProSceneRoleResolver::class, $inner->getClass());
    }

    public function test_stratzParametersHaveDefaults()
    {
        $container = $this->createContainer([[]]);

        $this->assertSame('https://api.stratz.com/graphql', $container->getParameter('dota2_api.stratz.base_url'));
        $this->assertNull($container->getParameter('dota2_api.stratz.token'));
    }

    public function test_stratzParametersAreTakenFromConfig()
    {
        $container = $this->createContainer([['stratz' => ['base_url' => 'https://example.test/gql', 'token' => 'tok']]]);

        $this->assertSame('https://example.test/gql', $container->getParameter('dota2_api.stratz.base_url'));
        $this->assertSame('tok', $container->getParameter('dota2_api.stratz.token'));
    }

    public function test_stratzClientIsBuiltFromContainer()
    {
        $container = $this->createContainer([[]]);
        $container->compile();

        $stratz = $container->get(Dota2ApiClient::class)->stratz();

        $this->assertInstanceOf(StratzMatchService::class, $stratz->matches());
        $this->assertInstanceOf(StratzTeamSource::class, $stratz->teams());
    }

    public function test_bothSourcesAreAvailableTogether()
    {
        $container = $this->createContainer([[]]);
        $container->compile();

        $client = $container->get(Dota2ApiClient::class);

        $this->assertInstanceOf(MatchService::class, $client->openDota()->matches());
        $this->assertInstanceOf(OpenDotaTeamSource::class, $client->openDota()->teams());
        $this->assertInstanceOf(StratzMatchService::class, $client->stratz()->matches());
        $this->assertInstanceOf(StratzTeamSource::class, $client->stratz()->teams());
        $this->assertNotSame($client->openDota()->matches(), $client->stratz()->matches());
    }

    public function test_stratzServicesArePrivateAndOnlyClientIsPublic()
    {
        $container = $this->createContainer([[]]);
        $ids = [
            StratzClient::class,
            StratzMatchService::class,
            StratzMatchSourceInterface::class,
            StratzTeamSourceInterface::class,
            StratzSourceFactory::class,
            GraphQlGateway::class,
            'dota2_api.stratz.config',
            'dota2_api.stratz.http_client',
        ];

        foreach ($ids as $id) {
            $this->assertFalse($container->getDefinition($id)->isPublic(), $id);
        }
        $this->assertTrue($container->getDefinition(Dota2ApiClient::class)->isPublic());

        $container->compile();

        foreach ($ids as $id) {
            $this->assertFalse($container->has($id), $id);
        }
    }

    public function test_applicationHttpClientIsNotReplacedWithStratzConfigured()
    {
        $container = $this->createContainer([['stratz' => ['token' => 'tok']]]);
        $container->compile();

        $this->assertInstanceOf(MockHttpClient::class, $container->get(HttpClientInterface::class));
        $this->assertInstanceOf(Dota2ApiClient::class, $container->get(Dota2ApiClient::class));
    }

    public function test_stratzClientConfigReceivesBaseUrlAndToken()
    {
        $container = $this->createContainer([[
            'stratz' => ['base_url' => 'https://example.test/gql', 'token' => 'tok'],
            'timeout' => 15,
            'max_retries' => 2,
        ]]);
        $container->getDefinition('dota2_api.stratz.config')->setPublic(true);
        $container->compile();

        /** @var ClientConfig $config */
        $config = $container->get('dota2_api.stratz.config');

        $this->assertSame('https://example.test/gql', $config->getBaseUrl());
        $this->assertSame('tok', $config->getApiKey());
        $this->assertSame(15, $config->getTimeout());
        $this->assertSame(2, $config->getMaxRetries());
    }

    public function test_stratzClientConfigDefaultsWithoutToken()
    {
        $container = $this->createContainer([[]]);
        $container->getDefinition('dota2_api.stratz.config')->setPublic(true);
        $container->compile();

        /** @var ClientConfig $config */
        $config = $container->get('dota2_api.stratz.config');

        $this->assertSame('https://api.stratz.com/graphql', $config->getBaseUrl());
        $this->assertNull($config->getApiKey());
    }

    public function test_stratzSourcesAreCreatedFromSourceFactory()
    {
        $container = $this->createContainer([[]]);
        foreach ([StratzMatchSourceInterface::class, StratzTeamSourceInterface::class] as $id) {
            $container->getDefinition($id)->setPublic(true);
        }
        $container->compile();

        $this->assertInstanceOf(StratzMatchSource::class, $container->get(StratzMatchSourceInterface::class));
        $this->assertInstanceOf(StratzTeamSource::class, $container->get(StratzTeamSourceInterface::class));
    }

    private function createContainer(array $configs): ContainerBuilder
    {
        $container = new ContainerBuilder();

        // http_client приложения, как его регистрирует framework-bundle
        $container->register('http_client', MockHttpClient::class)->setPublic(true);
        $container->setAlias(HttpClientInterface::class, 'http_client')->setPublic(true);

        (new Dota2ApiExtension())->load($configs, $container);

        return $container;
    }
}
