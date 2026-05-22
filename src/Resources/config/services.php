<?php
declare(strict_types=1);

use Histel951\Dota2Api\Client\Dota2ApiClient;
use Histel951\Dota2Api\Client\Dota2ApiClientBuilder;
use Histel951\Dota2Api\Domain\Services\RoleResolverInterface;
use Histel951\Dota2Api\Infrastructure\Http\ConfigurationHttpClient;
use Histel951\Dota2Api\Infrastructure\Services\ProSceneRoleResolver;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $configurator): void {

    $services = $configurator->services();

    $services
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->set(
        RoleResolverInterface::class,
        ProSceneRoleResolver::class
    );

    $services->set(ConfigurationHttpClient::class)
        ->args([
            '$baseUrl' => param('dota2_api.base_url'),
            '$timeout' => param('dota2_api.timeout'),
            '$apiSource' => param('dota2_api.source'),
            '$apiKey' => param('dota2_api.api_key'),
        ]);

    $services->set(Dota2ApiClientBuilder::class);

    $services->set(Dota2ApiClient::class)
        ->factory([
            service(Dota2ApiClientBuilder::class),
            'build',
        ])
    ->lazy();
};