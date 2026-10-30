<?php
declare(strict_types = 1);

use function Symfony\Component\DependencyInjection\Loader\Configurator\inline_service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use Histel951\Dota2Api\Client\Config\ClientConfig;
use Histel951\Dota2Api\OpenDota\Infrastructure\ApiGateway;
use Histel951\Dota2Api\OpenDota\Infrastructure\ApiGatewayInterface;
use Histel951\Dota2Api\OpenDota\Infrastructure\OpenDotaHttpClientFactory;
use Histel951\Dota2Api\OpenDota\Infrastructure\OpenDotaSourceFactory;
use Histel951\Dota2Api\OpenDota\Match\Application\MatchService;
use Histel951\Dota2Api\OpenDota\Match\Port\MatchSourceInterface;
use Histel951\Dota2Api\OpenDota\OpenDotaClient;
use Histel951\Dota2Api\OpenDota\Team\Port\TeamSourceInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Граф OpenDota: конфиг, собственный HTTP-клиент, шлюз, источники, сервис матчей и OpenDotaClient.
 */
return static function (ContainerConfigurator $configurator): void {

    $services = $configurator->services();

    $services
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->set('dota2_api.opendota.config', ClientConfig::class)
        ->args([
            '$baseUrl' => param('dota2_api.opendota.base_url'),
            '$apiKey' => param('dota2_api.opendota.api_key'),
            '$timeout' => param('dota2_api.timeout'),
            '$maxRetries' => param('dota2_api.max_retries'),
        ]);

    // собственный клиент бандла — глобальный http_client приложения не подменяется
    $services->set('dota2_api.opendota.http_client', HttpClientInterface::class)
        ->factory([OpenDotaHttpClientFactory::class, 'create'])
        ->args([
            inline_service(HttpClientInterface::class)->factory([HttpClient::class, 'create']),
            service('dota2_api.opendota.config'),
        ]);

    $services->set(ApiGateway::class)
        ->arg('$httpClient', service('dota2_api.opendota.http_client'));

    $services->alias(ApiGatewayInterface::class, ApiGateway::class);

    $services->set(OpenDotaSourceFactory::class);

    $services->set(MatchSourceInterface::class)
        ->factory([service(OpenDotaSourceFactory::class), 'createMatchSource']);

    $services->set(TeamSourceInterface::class)
        ->factory([service(OpenDotaSourceFactory::class), 'createTeamSource']);

    $services->set(MatchService::class);

    $services->set(OpenDotaClient::class);
};
