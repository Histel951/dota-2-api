<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Client;

use Histel951\Dota2Api\Client\Config\ClientConfig;
use Histel951\Dota2Api\OpenDota\Infrastructure\ApiGateway;
use Histel951\Dota2Api\OpenDota\Infrastructure\OpenDotaHttpClientFactory;
use Histel951\Dota2Api\OpenDota\Infrastructure\OpenDotaSourceFactory;
use Histel951\Dota2Api\OpenDota\Match\Application\MatchService;
use Histel951\Dota2Api\OpenDota\Match\Domain\Role\PrefilledRoleResolver;
use Histel951\Dota2Api\OpenDota\Match\Domain\Role\ProSceneRoleResolver;
use Histel951\Dota2Api\OpenDota\OpenDotaClient;
use Histel951\Dota2Api\Stratz\Infrastructure\GraphQlGateway;
use Histel951\Dota2Api\Stratz\Infrastructure\StratzHttpClientFactory;
use Histel951\Dota2Api\Stratz\Infrastructure\StratzSourceFactory;
use Histel951\Dota2Api\Stratz\Match\Application\MatchService as StratzMatchService;
use Histel951\Dota2Api\Stratz\StratzClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Сборка клиента без Symfony DI. В Symfony тот же граф собирает Dota2ApiExtension
 */
final readonly class Dota2ApiClientBuilder
{
    public function __construct(
        private HttpClientInterface $client,
        private ?ClientConfig $openDota = null,
        private ?ClientConfig $stratz = null,
    )
    {
    }

    /**
     * Без конфига источника используется его публичный адрес без ключа (STRATZ без токена
     * отвечает отказом на запросы).
     */
    public function build(): Dota2ApiClient
    {
        return new Dota2ApiClient(
            openDota: $this->buildOpenDota(),
            stratz: $this->buildStratz(),
        );
    }

    private function buildOpenDota(): OpenDotaClient
    {
        $config = $this->openDota ?? new ClientConfig(baseUrl: OpenDotaHttpClientFactory::DEFAULT_BASE_URL);
        $factory = new OpenDotaSourceFactory(
            new ApiGateway(OpenDotaHttpClientFactory::create($this->client, $config))
        );

        return new OpenDotaClient(
            matches: new MatchService(
                source: $factory->createMatchSource(),
                roleResolver: new PrefilledRoleResolver(inner: new ProSceneRoleResolver()),
            ),
            teams: $factory->createTeamSource(),
        );
    }

    private function buildStratz(): StratzClient
    {
        $config = $this->stratz ?? new ClientConfig(baseUrl: StratzHttpClientFactory::DEFAULT_BASE_URL);
        $factory = new StratzSourceFactory(
            new GraphQlGateway(StratzHttpClientFactory::create($this->client, $config))
        );

        return new StratzClient(
            matches: new StratzMatchService(source: $factory->createMatchSource()),
            teams: $factory->createTeamSource(),
        );
    }
}
