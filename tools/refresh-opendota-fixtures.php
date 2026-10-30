<?php
declare(strict_types = 1);

/**
 * Dev-скрипт: скачивает живые ответы OpenDota, сравнивает ключи с фикстурами
 * и проверяет, что поля, которые читает код бандла, на месте.
 *
 *   php tools/refresh-opendota-fixtures.php                  dry-run: дифф и проверка, ничего не пишет
 *   php tools/refresh-opendota-fixtures.php --write          перезаписать фикстуры команд
 *   php tools/refresh-opendota-fixtures.php --write --include-match   то же + фикстуру матча
 *
 * Exit code: 1 - ожидаемое кодом поле пропало у источника или сетевая ошибка (фикстуры не тронуты);
 *            0 - иначе.
 * Если задан OPENDOTA_API_KEY, он передаётся в query "api_key".
 */

use Histel951\Dota2Api\Tests\Support\OpenDota\KeyDiff;
use Histel951\Dota2Api\Tests\Support\OpenDota\SourceFieldsChecker;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\RetryableHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

require __DIR__ . '/../vendor/autoload.php';

const FIXTURES_DIR = __DIR__ . '/../tests/Fixtures/opendota';
const LIST_LIMIT = 20;
const MAX_ATTEMPTS = 3;
const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

$options = getopt('', ['write', 'include-match', 'help']);

if (isset($options['help'])) {
    echo <<<HELP
    Usage: php tools/refresh-opendota-fixtures.php [--write] [--include-match] [--help]

      (no flags)       dry-run: download, print key diff and checker violations, write nothing
      --write          overwrite team fixtures (compact JSON, lists truncated to 20 items)
      --include-match  also allow overwriting the match fixture (pretty-printed JSON)
      --help           show this help

    Env: OPENDOTA_API_KEY - optional, sent as query "api_key".
    Exit code 1: a field expected by the code is missing in the live response, or a network error.

    HELP;
    exit(0);
}

$write = isset($options['write']);
$includeMatch = isset($options['include-match']);

/**
 * Эндпоинты: фикстура, чекер, список ли (обрезается до LIST_LIMIT), pretty-print ли.
 *
 * @var list<array{endpoint: string, file: string, check: callable(array): array, truncate: bool, match: bool}> $targets
 */
$targets = [
    [
        'endpoint' => 'matches/8794744726',
        'file' => 'match_8794744726.json',
        'check' => SourceFieldsChecker::checkMatch(...),
        'truncate' => false,
        'match' => true,
    ],
    [
        'endpoint' => 'teams',
        'file' => 'teams.json',
        'check' => SourceFieldsChecker::checkTeams(...),
        'truncate' => true,
        'match' => false,
    ],
    [
        'endpoint' => 'teams/8261500',
        'file' => 'team_8261500.json',
        'check' => SourceFieldsChecker::checkTeam(...),
        'truncate' => false,
        'match' => false,
    ],
    [
        'endpoint' => 'teams/8261500/matches',
        'file' => 'team_8261500_matches.json',
        'check' => SourceFieldsChecker::checkTeamMatches(...),
        'truncate' => true,
        'match' => false,
    ],
    [
        'endpoint' => 'teams/8261500/players',
        'file' => 'team_8261500_players.json',
        'check' => SourceFieldsChecker::checkTeamPlayers(...),
        'truncate' => false,
        'match' => false,
    ],
    [
        'endpoint' => 'teams/8261500/heroes',
        'file' => 'team_8261500_heroes.json',
        'check' => SourceFieldsChecker::checkTeamHeroes(...),
        'truncate' => false,
        'match' => false,
    ],
];

/**
 * Скачивает эндпоинт (до MAX_ATTEMPTS попыток на сетевые ошибки, 429 и 5xx).
 *
 * @return array<mixed>
 */
function download(HttpClientInterface $client, string $endpoint, ?string $apiKey): array
{
    $query = $apiKey !== null ? ['api_key' => $apiKey] : [];

    return $client->request('GET', $endpoint, ['query' => $query])->toArray();
}

function encode(mixed $data, bool $pretty): string
{
    $flags = JSON_FLAGS | ($pretty ? JSON_PRETTY_PRINT : 0);

    return json_encode($data, $flags | JSON_THROW_ON_ERROR) . "\n";
}

$apiKey = getenv('OPENDOTA_API_KEY') ?: null;

$client = new RetryableHttpClient(
    HttpClient::create(['base_uri' => 'https://api.opendota.com/api/', 'timeout' => 30]),
    maxRetries: MAX_ATTEMPTS - 1,
);

echo sprintf("Mode: %s%s\n\n", $write ? 'WRITE' : 'dry-run', $write && $includeMatch ? ' (+ match)' : '');

// 1. Сначала скачиваем всё: при сетевой ошибке фикстуры не трогаем
$live = [];
foreach ($targets as $target) {
    try {
        $live[$target['endpoint']] = download($client, $target['endpoint'], $apiKey);
    } catch (Throwable $e) {
        fwrite(
            STDERR,
            sprintf(
                "ERROR: %s failed after %d attempt(s): %s\nFixtures were not touched.\n",
                $target['endpoint'],
                MAX_ATTEMPTS,
                $e->getMessage(),
            ),
        );
        exit(1);
    }
}

// 2. Дифф с фикстурами и проверка полей, которые читает код
$hasViolations = false;
$toWrite = [];

foreach ($targets as $target) {
    $endpoint = $target['endpoint'];
    $path = FIXTURES_DIR . '/' . $target['file'];
    $response = $live[$endpoint];

    echo "== {$endpoint} -> {$target['file']}\n";

    $fixture = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
    if ($fixture === null) {
        echo "  fixture missing or unreadable, diff skipped\n";
    } else {
        $lines = KeyDiff::format(KeyDiff::compare($response, $fixture));
        echo $lines === [] ? "  no key changes\n" : '  ' . implode("\n  ", $lines) . "\n";
    }

    $violations = ($target['check'])($response);
    foreach ($violations as $violation) {
        echo "  VIOLATION: {$violation}\n";
    }
    $hasViolations = $hasViolations || $violations !== [];

    if ($violations !== []) {
        echo "  fixture will not be overwritten (live response lacks expected fields)\n";
    } elseif ($target['match'] && !$includeMatch) {
        echo "  match fixture is protected, pass --include-match to overwrite\n";
    } else {
        $toWrite[$endpoint] = $target['truncate'] ? array_slice($response, 0, LIST_LIMIT) : $response;
    }

    echo "\n";
}

// 3. Запись (только с --write)
if ($write) {
    foreach ($targets as $target) {
        if (!array_key_exists($target['endpoint'], $toWrite)) {
            continue;
        }

        $path = FIXTURES_DIR . '/' . $target['file'];
        file_put_contents($path, encode($toWrite[$target['endpoint']], $target['match']));
        echo "written: {$target['file']}\n";
    }
} else {
    echo "Dry-run: no files were written.\n";
}

if ($hasViolations) {
    fwrite(STDERR, "FAILED: a field expected by the code is missing in the live response.\n");
    exit(1);
}

exit(0);
