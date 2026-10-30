<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Client;

use Histel951\Dota2Api\Exception\ClientConfigurationException;
use Histel951\Dota2Api\Infrastructure\Http\ApiGateway;
use Histel951\Dota2Api\Client\Config\ClientConfig;
use Histel951\Dota2Api\Client\Config\ApiSource;
use Histel951\Dota2Api\Infrastructure\OpenDota\OpenDotaHttpClientFactory;
use Histel951\Dota2Api\Infrastructure\OpenDota\Provider\OpenDotaProviderFactory;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Сборка клиента без Symfony DI. В Symfony тот же граф собирает Dota2ApiExtension
 */
final class Dota2ApiClientBuilder
{
    public function __construct(
        private readonly ClientConfig $cfg,
        private readonly HttpClientInterface $client,
    )
    {
    }

    /**
     * @throws ClientConfigurationException
     */
    public function build(): Dota2ApiClient
    {
        $factory = match ($this->cfg->getApiSource()) {
            ApiSource::OPENDOTA => new OpenDotaProviderFactory(
                new ApiGateway(OpenDotaHttpClientFactory::create($this->client, $this->cfg))
            ),
            ApiSource::STRATZ => throw new ClientConfigurationException('To be implemented'),
        };

        return new Dota2ApiClient(
            matchesProvider: $factory->createMatchProvider(),
            teamProvider: $factory->createTeamProvider(),
        );
    }
}
