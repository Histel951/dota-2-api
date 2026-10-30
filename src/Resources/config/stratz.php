<?php
declare(strict_types=1);

use function Symfony\Component\DependencyInjection\Loader\Configurator\inline_service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use Histel951\Dota2Api\Client\Config\ClientConfig;
use Histel951\Dota2Api\Stratz\Infrastructure\GraphQlGateway;
use Histel951\Dota2Api\Stratz\Infrastructure\StratzHttpClientFactory;
use Histel951\Dota2Api\Stratz\Infrastructure\StratzSourceFactory;
use Histel951\Dota2Api\Stratz\Match\Application\MatchService as StratzMatchService;
use Histel951\Dota2Api\Stratz\Match\Port\MatchSourceInterface as StratzMatchSourceInterface;
use Histel951\Dota2Api\Stratz\StratzClient;
use Histel951\Dota2Api\Stratz\Team\Port\TeamSourceInterface as StratzTeamSourceInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Граф STRATZ: конфиг, собственный HTTP-клиент, GraphQL-шлюз, источники, сервис матчей и StratzClient.
 */
return static function (ContainerConfigurator $configurator): void {

    $services = $configurator->services();

    $services
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->set('dota2_api.stratz.config', ClientConfig::class)
        ->args([
            '$baseUrl' => param('dota2_api.stratz.base_url'),
            '$apiKey' => param('dota2_api.stratz.token'),
            '$timeout' => param('dota2_api.timeout'),
            '$maxRetries' => param('dota2_api.max_retries'),
        ]);

    // собственный клиент бандла — глобальный http_client приложения не подменяется
    $services->set('dota2_api.stratz.http_client', HttpClientInterface::class)
        ->factory([StratzHttpClientFactory::class, 'create'])
        ->args([
            inline_service(HttpClientInterface::class)->factory([HttpClient::class, 'create']),
            service('dota2_api.stratz.config'),
        ]);

    $services->set(GraphQlGateway::class)
        ->arg('$httpClient', service('dota2_api.stratz.http_client'));

    $services->set(StratzSourceFactory::class);

    $services->set(StratzMatchSourceInterface::class)
        ->factory([service(StratzSourceFactory::class), 'createMatchSource']);

    $services->set(StratzTeamSourceInterface::class)
        ->factory([service(StratzSourceFactory::class), 'createTeamSource']);

    $services->set(StratzMatchService::class);

    $services->set(StratzClient::class);
};
