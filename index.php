<?php

use Histel951\Dota2Api\Client\Dota2ApiClientBuilder;
use Histel951\Dota2Api\Domain\Common\Enums\PlayerRole;
use Histel951\Dota2Api\Domain\Common\ValueObjects\MatchId;
use Histel951\Dota2Api\Domain\Common\ValueObjects\TeamId;
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
$detail = $matchesProvider->getMatch(new MatchId(8866148821));

$matchDetail = $detail->getMatchDetail();

echo "=== Match ID: " . $matchDetail->getId()->getValue() . " ===\n\n";
echo "Duration: " . $matchDetail->getDuration()->getSeconds() . " seconds\n";
echo "Winner: " . ($matchDetail->getResult()->getWinner()->getSide() === Side::RADIANT ? 'Radiant' : 'Dire') . "\n\n";
echo "  Region: " . $matchDetail->getRegion()->getName() . "\n\n";

echo "=== Players Statistics ===\n\n";

foreach ($matchDetail->getPlayers()->getValue() as $index => $player) {
    echo "Player " . ($index + 1) . ":\n";
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
    echo "    Observer Takeovers: " . $player->getWarding()->getObserverTakeovers()->getValue() . "\n";
    echo "    Observers Placed: " . $player->getWarding()->getObserversPlaced()->getValue() . "\n";
    echo "\n";
}