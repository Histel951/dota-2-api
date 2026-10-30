<?php
declare(strict_types=1);

use Histel951\Dota2Api\Client\Dota2ApiClient;
use Histel951\Dota2Api\Domain\Entities\Match\Services\PrefilledRoleResolver;
use Histel951\Dota2Api\Domain\Entities\Match\Services\ProSceneRoleResolver;
use Histel951\Dota2Api\Domain\Providers\MatchesProviderInterface;
use Histel951\Dota2Api\Team\Port\TeamSourceInterface;
use Histel951\Dota2Api\Domain\Services\RoleResolverInterface;
use Histel951\Dota2Api\Infrastructure\Http\ApiGateway;
use Histel951\Dota2Api\Client\Config\ClientConfig;
use Histel951\Dota2Api\Infrastructure\Http\ApiGatewayInterface;
use Histel951\Dota2Api\Infrastructure\Provider\Contracts\ProviderFactory;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use function Symfony\Component\DependencyInjection\Loader\Configurator\inline_service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * Реализация ProviderFactory, фабрика 'dota2_api.http_client' и параметры
 * dota2_api.base_url / dota2_api.api_key задаются в Dota2ApiExtension по source
 */
return static function (ContainerConfigurator $configurator): void {

    $services = $configurator->services();

    $services
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->set(ClientConfig::class)
        ->args([
            '$baseUrl' => param('dota2_api.base_url'),
            '$timeout' => param('dota2_api.timeout'),
            '$apiSource' => param('dota2_api.source'),
            '$apiKey' => param('dota2_api.api_key'),
            '$maxRetries' => param('dota2_api.max_retries'),
        ]);

    // собственный клиент бандла — глобальный http_client приложения не подменяется
    $services->set('dota2_api.http_client', HttpClientInterface::class)
        ->args([
            inline_service(HttpClientInterface::class)->factory([HttpClient::class, 'create']),
            service(ClientConfig::class),
        ]);

    $services->set(ApiGateway::class)
        ->arg('$httpClient', service('dota2_api.http_client'));

    $services->alias(ApiGatewayInterface::class, ApiGateway::class);

    // сначала роли из position_est, при неполном наборе — делегирование эвристике ProSceneRoleResolver
    $services->set(RoleResolverInterface::class, PrefilledRoleResolver::class)
        ->arg('$inner', inline_service(ProSceneRoleResolver::class));

    $services->set(MatchesProviderInterface::class)
        ->factory([service(ProviderFactory::class), 'createMatchProvider']);

    $services->set(TeamSourceInterface::class)
        ->factory([service(ProviderFactory::class), 'createTeamProvider']);

    $services->set(Dota2ApiClient::class)
        ->public();
};
