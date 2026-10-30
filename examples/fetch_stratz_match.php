<?php
declare(strict_types = 1);

/**
 * Сборка клиента без Symfony и вывод матча STRATZ.
 *
 *   STRATZ_TOKEN=<token> php examples/fetch_stratz_match.php [match_id]
 */

use Histel951\Dota2Api\Client\Config\ClientConfig;
use Histel951\Dota2Api\Client\Dota2ApiClientBuilder;
use Histel951\Dota2Api\SharedKernel\MatchId;
use Histel951\Dota2Api\SharedKernel\Side;
use Histel951\Dota2Api\Stratz\Infrastructure\StratzHttpClientFactory;
use Histel951\Dota2Api\Stratz\Match\Domain\TeamSide;
use Symfony\Component\HttpClient\HttpClient;

require_once __DIR__ . '/../vendor/autoload.php';

$token = getenv('STRATZ_TOKEN');
if (!is_string($token) || $token === '') {
    fwrite(STDERR, "STRATZ_TOKEN is not set\n");
    exit(1);
}

$client = (new Dota2ApiClientBuilder(
    HttpClient::create(),
    stratz: new ClientConfig(
        baseUrl: StratzHttpClientFactory::DEFAULT_BASE_URL,
        apiKey: $token,
        timeout: 120,
    ),
))->build();

$result = $client->stratz()->matches()->getMatch(new MatchId((int) ($argv[1] ?? 9026055036)));
$match = $result->getMatchDetail();

if (null === $match) {
    echo "Failed to map match: " . $result->getError()?->getMessage() . "\n";
    exit(1);
}

$teamLabel = static fn (TeamSide $team): string => sprintf(
    '%s (%s)',
    $team->getTeamName()->getValue() ?? 'no team',
    $team->getSide() === Side::RADIANT ? 'Radiant' : 'Dire',
);

echo "=== Match ID: " . $match->getId()->getValue() . " ===\n\n";
echo "Duration: " . $match->getDuration()->getSeconds() . " seconds\n";
echo "Start: " . $match->getStartTime()->getValue()->format('Y-m-d H:i T') . "\n";
echo "Region: " . $match->getRegion()->getName() . "\n";
echo "Radiant: " . $teamLabel($match->getRadiant()) . "\n";
echo "Dire: " . $teamLabel($match->getDire()) . "\n";
echo "Winner: " . $teamLabel($match->getWinner()) . "\n";
echo "Parsed: " . ($match->isParsed() ? 'yes' : 'no') . "\n";
echo "Draft: " . (null === $match->getDraft()
    ? 'n/a'
    : count($match->getDraft()->getPicks()) . ' picks, ' . count($match->getDraft()->getBans()) . ' bans') . "\n\n";

echo "=== Players ===\n\n";

foreach ($match->getPlayers() as $index => $player) {
    $identity = $player->getIdentity();
    $kda = $player->getKda();
    $economy = $player->getEconomy();
    $parsed = $player->getParsedStats();

    echo "Player " . ($index + 1) . ": " . ($identity->getProName() ?? $identity->getPersonaName())?->getValue() . "\n";
    echo "  ID: " . $identity->getPlayerId()->getValue() . "\n";
    echo "  Side: " . ($identity->isRadiant() ? 'Radiant' : 'Dire') . "\n";
    echo "  Hero ID: " . $identity->getHeroId()->getValue() . "\n";
    echo "  Lane: " . $identity->getLane()->name . "\n";
    echo "  Position: " . ($identity->getPosition()?->name ?? 'n/a') . "\n";
    echo "  KDA: " . $kda->getKills() . "/" . $kda->getDeaths() . "/" . $kda->getAssists() . "\n";
    echo "  GPM/XPM: " . $economy->getGpm()->getValue() . "/" . $economy->getXpm()->getValue() . "\n";
    echo "  Net worth: " . $economy->getNetWorth()->getValue() . "\n";
    echo "  Last hits/denies: " . $player->getFarm()->getLastHits()->getValue() . "/" . $player->getFarm()->getDenies()->getValue() . "\n";
    echo "  Hero damage: " . $player->getDamage()->getHeroDamage()->getValue() . "\n";

    if (null !== $parsed) {
        echo "  Parsed stats:\n";
        echo "    Lane/neutral/ancient creeps: " . $parsed->getLaneCreeps()->getValue() . "/"
            . $parsed->getNeutralCreeps()->getValue() . "/" . $parsed->getAncientCreeps()->getValue() . "\n";
        echo "    Camps stacked: " . $parsed->getCampsStacked()->getValue() . "\n";
        echo "    Observers/sentries placed: " . $parsed->getObserversPlaced()->getValue() . "/" . $parsed->getSentriesPlaced()->getValue() . "\n";
        echo "    Observers/sentries destroyed: " . $parsed->getObserversDestroyed()->getValue() . "/" . $parsed->getSentriesDestroyed()->getValue() . "\n";
        echo "    Rune pickups: " . $parsed->getRunePickups()->getValue() . "\n";
        echo "    Smoke uses: " . $parsed->getSmokeUses()->getValue() . "\n";
        echo "    Stun duration: " . round($parsed->getStunDuration()->getSeconds(), 1) . " s\n";
        echo "    Buybacks: " . ($parsed->getBuybacks()?->getValue() ?? 'n/a') . "\n";
    }

    echo "\n";
}
