<?php

use Histel951\Dota2Api\Client\Dota2ApiClientBuilder;
use Histel951\Dota2Api\Domain\Common\ValueObjects\MatchId;
use Histel951\Dota2Api\Infrastructure\Http\ConfigurationHttpClient;
use Histel951\Dota2Api\Infrastructure\Http\Enums\ApiSource;
use Symfony\Component\HttpClient\HttpClient;

require_once 'vendor/autoload.php';

$client = (new Dota2ApiClientBuilder(new ConfigurationHttpClient(
    baseUrl: 'https://api.opendota.com/api/',
    apiSource: ApiSource::OPENDOTA,
    timeout: 600
), HttpClient::create()))->build();


$matchesProvider = $client->matches();
$detail = $matchesProvider->getMatch(new MatchId(8787299097));
var_dump($detail->getPlayers()->getValue());