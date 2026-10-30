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
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Сборка клиента без Symfony DI. В Symfony тот же граф собирает Dota2ApiExtension
 */
final readonly class Dota2ApiClientBuilder
{
    public function __construct(
        private HttpClientInterface $client,
        private ?ClientConfig $openDota = null,
    )
    {
    }

    /**
     * Без конфига OpenDota используется публичный адрес без ключа.
     */
    public function build(): Dota2ApiClient
    {
        $config = $this->openDota ?? new ClientConfig(baseUrl: OpenDotaHttpClientFactory::DEFAULT_BASE_URL);
        $factory = new OpenDotaSourceFactory(
            new ApiGateway(OpenDotaHttpClientFactory::create($this->client, $config))
        );

        return new Dota2ApiClient(
            openDota: new OpenDotaClient(
                matches: new MatchService(
                    source: $factory->createMatchSource(),
                    roleResolver: new PrefilledRoleResolver(inner: new ProSceneRoleResolver()),
                ),
                teams: $factory->createTeamSource(),
            ),
        );
    }
}
