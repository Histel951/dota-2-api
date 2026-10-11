<?php
declare(strict_types=1);

use Histel951\Dota2Api\Client\Dota2ApiClient;
use Histel951\Dota2Api\Domain\Entities\Match\Services\ProSceneRoleResolver;
use Histel951\Dota2Api\Domain\Providers\MatchesProviderInterface;
use Histel951\Dota2Api\Domain\Providers\TeamProviderInterface;
use Histel951\Dota2Api\Domain\Services\RoleResolverInterface;
use Histel951\Dota2Api\Infrastructure\Http\ApiGateway;
use Histel951\Dota2Api\Infrastructure\Http\ConfigurationHttpClient;
use Histel951\Dota2Api\Infrastructure\Http\Contracts\ApiGatewayInterface;
use Histel951\Dota2Api\Infrastructure\Provider\Contracts\ProviderFactory;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use function Symfony\Component\DependencyInjection\Loader\Configurator\inline_service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * Реализация ProviderFactory и фабрика 'dota2_api.http_client' выбираются в Dota2ApiExtension по source
 */
return static function (ContainerConfigurator $configurator): void {

    $services = $configurator->services();

    $services
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->set(ConfigurationHttpClient::class)
        ->args([
            '$baseUrl' => param('dota2_api.base_url'),
            '$timeout' => param('dota2_api.timeout'),
            '$apiSource' => param('dota2_api.source'),
            '$apiKey' => param('dota2_api.api_key'),
        ]);

    // собственный клиент бандла — глобальный http_client приложения не подменяется
    $services->set('dota2_api.http_client', HttpClientInterface::class)
        ->args([
            inline_service(HttpClientInterface::class)->factory([HttpClient::class, 'create']),
            service(ConfigurationHttpClient::class),
        ]);

    $services->set(ApiGateway::class)
        ->arg('$httpClient', service('dota2_api.http_client'));

    $services->alias(ApiGatewayInterface::class, ApiGateway::class);

    $services->set(RoleResolverInterface::class, ProSceneRoleResolver::class);

    $services->set(MatchesProviderInterface::class)
        ->factory([service(ProviderFactory::class), 'createMatchProvider']);

    $services->set(TeamProviderInterface::class)
        ->factory([service(ProviderFactory::class), 'createTeamProvider']);

    $services->set(Dota2ApiClient::class)
        ->public();
};
