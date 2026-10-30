<?php
declare(strict_types = 1);

/**
 * Dev-скрипт: скачивает живые ответы STRATZ теми же запросами, что и бандл, сравнивает ключи с фикстурами.
 *
 *   php tools/refresh-stratz-fixtures.php                  dry-run: дифф, ничего не пишет
 *   php tools/refresh-stratz-fixtures.php --write          перезаписать фикстуры команд
 *   php tools/refresh-stratz-fixtures.php --write --include-match   то же + фикстуры матчей
 *
 * Токен: переменная окружения STRATZ_TOKEN или ключ STRATZ_TOKEN в .env.local (в вывод не попадает).
 * Лимит STRATZ - 150 запросов в минуту, скрипт делает 6 запросов подряд.
 *
 * Exit code: 1 - нет токена, сетевая ошибка, статус не 200, errors в ответе или корневое поле null
 *            (фикстуры не тронуты); 0 - иначе.
 */

use Histel951\Dota2Api\Client\Config\ClientConfig;
use Histel951\Dota2Api\SharedKernel\MatchId;
use Histel951\Dota2Api\Stratz\Infrastructure\GraphQlGateway;
use Histel951\Dota2Api\Stratz\Infrastructure\Match\MatchQuery;
use Histel951\Dota2Api\Stratz\Infrastructure\StratzHttpClientFactory;
use Histel951\Dota2Api\Stratz\Infrastructure\Team\TeamQuery;
use Histel951\Dota2Api\Tests\Support\KeyDiff;
use Symfony\Component\HttpClient\HttpClient;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

require __DIR__ . '/../vendor/autoload.php';

const FIXTURES_DIR = __DIR__ . '/../tests/Fixtures/stratz';
const LIST_LIMIT = 20;
const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
const TEAM_ID = 8261500;

$options = getopt('', ['write', 'include-match', 'help']);

if (isset($options['help'])) {
    echo <<<HELP
    Usage: php tools/refresh-stratz-fixtures.php [--write] [--include-match] [--help]

      (no flags)       dry-run: download, print key diff, write nothing
      --write          overwrite team fixtures (compact JSON, matches truncated to 20 items)
      --include-match  also allow overwriting match fixtures (pretty-printed JSON)
      --help           show this help

    Env: STRATZ_TOKEN - required (or STRATZ_TOKEN in .env.local), sent as Bearer token, never printed.
    Exit code 1: no token, network error, non-200 status, GraphQL errors or a null root field.

    HELP;
    exit(0);
}

$write = isset($options['write']);
$includeMatch = isset($options['include-match']);

function resolveToken(): ?string
{
    $token = getenv('STRATZ_TOKEN');
    if (is_string($token) && $token !== '') {
        return $token;
    }

    $envFile = __DIR__ . '/../.env.local';
    if (is_file($envFile)) {
        $env = parse_ini_file($envFile, false, INI_SCANNER_RAW);
        $token = is_array($env) ? ($env['STRATZ_TOKEN'] ?? null) : null;
        if (is_string($token)) {
            $token = trim($token, " \t\"'");

            return $token !== '' ? $token : null;
        }
    }

    return null;
}

/**
 * Значение по пути ключей из ответа; null, если на пути чего-то нет.
 *
 * @param array<mixed> $data
 * @param list<string> $path
 */
function dig(array $data, array $path): mixed
{
    foreach ($path as $key) {
        if (!is_array($data) || !array_key_exists($key, $data)) {
            return null;
        }
        $data = $data[$key];
    }

    return $data;
}

function encode(mixed $data, bool $pretty): string
{
    $flags = JSON_FLAGS | ($pretty ? JSON_PRETTY_PRINT : 0);

    return json_encode($data, $flags | JSON_THROW_ON_ERROR) . "\n";
}

$token = resolveToken();
if ($token === null) {
    fwrite(STDERR, "ERROR: STRATZ_TOKEN is not set (environment variable or .env.local).\n");
    exit(1);
}

$gateway = new GraphQlGateway(
    StratzHttpClientFactory::create(
        HttpClient::create(),
        new ClientConfig(StratzHttpClientFactory::DEFAULT_BASE_URL, $token, 90, 2),
    ),
);

$teamVariables = ['teamId' => TEAM_ID];
$matchA = MatchQuery::build([new MatchId(9026055036)]);
$matchB = MatchQuery::build([new MatchId(8794744726)]);

/**
 * Цели: фикстура, запрос, переменные, путь корневого поля для KeyDiff, список ли matches (обрезка), матч ли.
 *
 * @var list<array{file: string, query: string, variables: array<string, mixed>, root: list<string>, truncate: bool, match: bool}> $targets
 */
$targets = [
    [
        'file' => 'match_9026055036.json',
        'query' => $matchA['query'],
        'variables' => $matchA['variables'],
        'root' => ['data', 'm0'],
        'truncate' => false,
        'match' => true,
    ],
    [
        'file' => 'match_8794744726.json',
        'query' => $matchB['query'],
        'variables' => $matchB['variables'],
        'root' => ['data', 'm0'],
        'truncate' => false,
        'match' => true,
    ],
    [
        'file' => 'team_8261500.json',
        'query' => TeamQuery::TEAM,
        'variables' => $teamVariables,
        'root' => ['data', 'team'],
        'truncate' => false,
        'match' => false,
    ],
    [
        'file' => 'team_8261500_matches.json',
        'query' => TeamQuery::MATCHES,
        'variables' => $teamVariables,
        'root' => ['data', 'team', 'matches'],
        'truncate' => true,
        'match' => false,
    ],
    [
        'file' => 'team_8261500_players.json',
        'query' => TeamQuery::PLAYERS,
        'variables' => $teamVariables,
        'root' => ['data', 'team', 'players'],
        'truncate' => false,
        'match' => false,
    ],
    [
        'file' => 'team_8261500_heroes.json',
        'query' => TeamQuery::HEROES,
        'variables' => $teamVariables,
        'root' => ['data', 'team', 'heroes'],
        'truncate' => false,
        'match' => false,
    ],
];

echo sprintf("Mode: %s%s\n\n", $write ? 'WRITE' : 'dry-run', $write && $includeMatch ? ' (+ match)' : '');

// 1. Сначала скачиваем всё (запросы по одному): при ошибке фикстуры не трогаем
$live = [];
foreach ($targets as $target) {
    try {
        $response = $gateway->request($target['query'], $target['variables']);
        $gateway->decode($response); // статус 200, JSON, data без errors-only ответа
        $body = $response->toArray();
    } catch (Throwable $e) {
        fwrite(STDERR, sprintf("ERROR: %s failed: %s\nFixtures were not touched.\n", $target['file'], $e->getMessage()));
        exit(1);
    }

    if (isset($body['errors']) && $body['errors'] !== []) {
        $messages = array_map(
            static fn (mixed $error): string => is_array($error) && is_string($error['message'] ?? null)
                ? $error['message']
                : 'unknown error',
            (array) $body['errors'],
        );
        fwrite(STDERR, sprintf("ERROR: %s returned GraphQL errors: %s\nFixtures were not touched.\n", $target['file'], implode('; ', $messages)));
        exit(1);
    }

    if (dig($body, $target['root']) === null) {
        fwrite(STDERR, sprintf("ERROR: %s: root field %s is null.\nFixtures were not touched.\n", $target['file'], implode('.', $target['root'])));
        exit(1);
    }

    $live[$target['file']] = $body;
}

// 2. Дифф с фикстурами по развёрнутому корневому полю
$toWrite = [];

foreach ($targets as $target) {
    $file = $target['file'];
    $path = FIXTURES_DIR . '/' . $file;
    $body = $live[$file];

    echo "== {$file}\n";

    $fixture = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
    $fixtureRoot = is_array($fixture) ? dig($fixture, $target['root']) : null;

    if ($fixtureRoot === null) {
        echo "  fixture missing or unreadable, diff skipped\n";
    } else {
        $lines = KeyDiff::format(KeyDiff::compare(dig($body, $target['root']), $fixtureRoot));
        echo $lines === [] ? "  no key changes\n" : '  ' . implode("\n  ", $lines) . "\n";
    }

    if ($target['match'] && !$includeMatch) {
        echo "  match fixture is protected, pass --include-match to overwrite\n";
    } else {
        if ($target['truncate']) {
            $body['data']['team']['matches'] = array_slice($body['data']['team']['matches'], 0, LIST_LIMIT);
        }
        $toWrite[$file] = $body;
    }

    echo "\n";
}

// 3. Запись (только с --write)
if ($write) {
    foreach ($targets as $target) {
        if (!array_key_exists($target['file'], $toWrite)) {
            continue;
        }

        file_put_contents(FIXTURES_DIR . '/' . $target['file'], encode($toWrite[$target['file']], $target['match']));
        echo "written: {$target['file']}\n";
    }
} else {
    echo "Dry-run: no files were written.\n";
}

exit(0);
