<?php
declare(strict_types=1);

use Histel951\Dota2Api\Client\Dota2ApiClient;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * Публичный вход бандла. Графы источников (opendota.php, stratz.php) собираются отдельно.
 */
return static function (ContainerConfigurator $configurator): void {

    $services = $configurator->services();

    $services
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->set(Dota2ApiClient::class)
        ->public();
};
