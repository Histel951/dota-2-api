<?php

use Histel951\Dota2Api\Client\Dota2ApiClientBuilder;
use Histel951\Dota2Api\SharedKernel\MatchId;
use Histel951\Dota2Api\Match\Domain\Side\Side;
use Histel951\Dota2Api\Client\Config\ClientConfig;
use Histel951\Dota2Api\Client\Config\ApiSource;
use Symfony\Component\HttpClient\HttpClient;

require_once __DIR__ . '/../vendor/autoload.php';

$client = (new Dota2ApiClientBuilder(new ClientConfig(
    baseUrl: 'https://api.opendota.com/api/',
    apiSource: ApiSource::OPENDOTA,
    timeout: 600
), HttpClient::create()))->build();

$matchesProvider = $client->matches();
$detail = $matchesProvider->getMatch(new MatchId(8794744726));

$matchDetail = $detail->getMatchDetail();

if (null === $matchDetail) {
    echo "Failed to map match: " . $detail->getError()->getMessage() . "\n";
    exit(1);
}

echo "=== Match ID: " . $matchDetail->getId()->getValue() . " ===\n\n";
echo "Duration: " . $matchDetail->getDuration()->getSeconds() . " seconds\n";
echo "Winner: " . ($matchDetail->getResult()->getWinner()->getSide() === Side::RADIANT ? 'Radiant' : 'Dire') . "\n\n";
echo "  Region: " . $matchDetail->getRegion()->getName() . "\n\n";

echo "=== Players Statistics ===\n\n";

foreach ($matchDetail->getPlayers()->getValue() as $index => $player) {
    echo "Player " . ($index + 1) . ":\n";
    echo "  ID: " . $player->getIdentity()->getPlayerId()->getValue() . "\n\n";
    echo "  Side: " . ($player->getIdentity()->getSide() === Side::RADIANT ? 'Radiant' : 'Dire') . "\n";
    echo "  Hero ID: " . $player->getIdentity()->getHeroId()->getValue() . "\n";
    echo "  KDA: " . $player->getKda()->getKills() . "/" . $player->getKda()->getDeaths() . "/" . $player->getKda()->getAssists() . "\n";
    echo "  Name: " . $player->getIdentity()->getPlayerProName()->getValue() . "\n";
    echo "  Role: " . $player->getRole()->getValue()->name . "\n";
    echo "  Creeps Stats:\n";
    echo "    Last Hits: " . $player->getCreeps()->getLastHits()->getValue() . "\n";
    echo "    Denies: " . $player->getCreeps()->getDenies()->getValue() . "\n";
    echo "    Neutral Creeps: " . $player->getCreeps()->getNeutralCreeps()->getValue() . "\n";
    echo "    Ancient Creeps: " . $player->getCreeps()->getAncientCreeps()->getValue() . "\n";
    echo "    Camps Stacked: " . $player->getCreeps()->getCampsStacked()->getValue() . "\n";
    echo "  Objectives Stats:\n";
    echo "    Tormentor Kills: " . $player->getObjectives()->getTormentorKills()->getValue() . "\n";
    echo "    Roshan Kills: " . $player->getObjectives()->getRoshanKills()->getValue() . "\n";
    echo "  Utility Stats:\n";
    echo "    Smoke Uses: " . $player->getUtility()->getSmokeUses()->getValue() . "\n";
    echo "  Warding Stats:\n";
    echo "    Watcher: " . $player->getWarding()->getObserverTakeovers()->getValue() . "\n";
    echo "    Sentry Placed: " . $player->getWarding()->getSentryPlaced()->getValue() . "\n";
    echo "    Observers Placed: " . $player->getWarding()->getObserversPlaced()->getValue() . "\n";
    echo "    smoke uses: " . $player->getUtility()->getSmokeUses()->getValue() . "\n";
    echo "\n";
}