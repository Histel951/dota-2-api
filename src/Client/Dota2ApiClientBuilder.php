<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Client;

use Histel951\Dota2Api\Infrastructure\Exceptions\Dota2ApiClientBuilderException;
use Histel951\Dota2Api\Infrastructure\Http\ApiGateway;
use Histel951\Dota2Api\Infrastructure\Http\ConfigurationHttpClient;
use Histel951\Dota2Api\Infrastructure\Http\Enums\ApiSource;
use Histel951\Dota2Api\Infrastructure\Http\OpenDotaHttpClient;
use Histel951\Dota2Api\Infrastructure\Provider\OpenDotaProviderFactory;
use Symfony\Component\HttpClient\HttpClient;

final class Dota2ApiClientBuilder
{
    private ConfigurationHttpClient $cfg;

    public function withConfiguration(ConfigurationHttpClient $cfg): self
    {
        $clone = clone $this;
        $clone->cfg = $cfg;

        return $clone;
    }

    /**
     * @throws Dota2ApiClientBuilderException
     */
    public function build(): Dota2ApiClient
    {
        $httpClient = new OpenDotaHttpClient(
            HttpClient::create(),
            $this->cfg,
        );

        $gateway = new ApiGateway($httpClient);

        $factory = match ($this->cfg->getApiSource()) {
            ApiSource::OPENDOTA => new OpenDotaProviderFactory($gateway),
            ApiSource::STRATZ => throw new Dota2ApiClientBuilderException('To be implemented'),
        };

        return new Dota2ApiClient(
            matchesProvider: $factory->createMatchProvider(),
            teamProvider: $factory->createTeamProvider(),
        );
    }
}