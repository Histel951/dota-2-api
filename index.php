<?php

use Histel951\Dota2Api\Client\Dota2ApiClientBuilder;
use Histel951\Dota2Api\Domain\Common\Enums\PlayerRole;
use Histel951\Dota2Api\Domain\Common\ValueObjects\MatchId;
use Histel951\Dota2Api\Domain\Entities\Match\Enums\Side;
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
$detail = $matchesProvider->getMatches([
    new MatchId(8787299097),
    new MatchId(8824218541),
    new MatchId(8824123966),
    new MatchId(8823904596),
    new MatchId(8823789680),
    new MatchId(8822412074),
    new MatchId(8822593932),
    new MatchId(8822520406),
]);

foreach ($detail as $match) {
    if ($match->getError()) {
        echo $match->getError()->getMessage();
    } else {
        var_dump($match->getMatchDetail()
            ->getPlayers()
            ->getByRole(PlayerRole::MIDDLE, Side::RADIANT)
            ->getIdentity()
            ->getPlayerProName()
            ->getValue()
        );
    }
}