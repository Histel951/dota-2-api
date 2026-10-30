<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Tests\Unit\DependencyInjection;

use Histel951\Dota2Api\Client\Dota2ApiClient;
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
