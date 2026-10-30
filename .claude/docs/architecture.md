# Архитектура

## Слои и контексты (`src/`)

Код сгруппирован по контекстам (Match, Team, SharedKernel), внутри контекста — по понятию, а не по типу класса.

```
Dota2ApiBundle.php            — класс бандла (пустой, вся настройка в DI Extension)
DependencyInjection/          — Configuration (дерево конфига dota2_api) + Dota2ApiExtension
Resources/config/services.php — регистрация сервисов
Client/                       — публичная точка входа: Dota2ApiClient + Dota2ApiClientBuilder (без Symfony)
  Config/                     — ClientConfig (base_url, source, api_key, timeout, max_retries, options), enum ApiSource
Exception/                    — публичные исключения: Dota2ApiExceptionInterface (маркер), SourceRequestException,
                                SourceDataException, ClientConfigurationException
SharedKernel/                 — то, что нужно обоим контекстам: MatchId, TeamId, TeamName, PlayerId, PlayerName,
                                HeroId, Duration; Exception/ — DomainException (база доменных исключений) и их Invalid*
Match/
  Domain/                     — MatchDetail, MatchStartTime; Player/, Player/Stats/, Draft/, Side/, Region/
    Role/                     — RoleResolverInterface, PrefilledRoleResolver, ProSceneRoleResolver
    Exception/                — исключения контекста матча
  Application/                — MatchService (роли + обёртка ошибок), MatchFetchResult
  Port/                       — MatchSourceInterface (что умеет источник матчей)
Team/
  Domain/                     — Team, TeamTag, TeamStats, WinrateStats; History/, Roster/, Heroes/; Exception/
  Port/                       — TeamSourceInterface
Infrastructure/               — общее для всех источников
  Http/                       — ApiGateway (+ApiGatewayInterface), HttpMethod, HttpStatusCode
  Mapping/                    — AbstractMapper
  SourceFactoryInterface.php  — фабрика адаптеров источника (createMatchSource / createTeamSource)
  OpenDota/                   — всё, что специфично для OpenDota
    OpenDotaHttpClientFactory — base_uri, timeout, api_key, RetryableHttpClient
    OpenDotaSourceFactory     — реализация SourceFactoryInterface
    Match/                    — OpenDotaMatchSource, MatchExtractor, MatchMapper, PlayerMapper, DraftMapper, LaneNormalizer
    Team/                     — OpenDotaTeamSource, TeamMapper, TeamMatchMapper, TeamPlayerMapper, TeamHeroMapper
```

## Правила зависимостей

- `SharedKernel` ни от чего не зависит (вне себя — только `Exception\Dota2ApiExceptionInterface`).
- `Exception` ни от чего не зависит.
- `Match/Domain`, `Team/Domain` → `SharedKernel`. **Match и Team друг от друга не зависят.**
- `<Context>/Port` → свой Domain, `SharedKernel`, `Exception`.
- `Match/Application` → `Match/Domain`, `Match/Port`, `SharedKernel`, `Exception`.
- `Infrastructure` → всё, кроме `Match/Application`, `Client` (кроме `Client/Config`), `DependencyInjection`, `Resources`, `Dota2ApiBundle`.
- `Client`, `Client/Config`, `DependencyInjection`, `Resources`, `Dota2ApiBundle` — composition root, без ограничений. От Infrastructure зависят только они.

Правила проверяет `tests/Unit/Architecture/LayerDependenciesTest.php`: парсит все файлы `src/` (`nikic/php-parser`, dev), собирает все ссылки на классы бандла — `use` и полные имена без `use` — и сверяет слой файла со слоем цели по белому списку. Файл или цель в неизвестном слое — тоже нарушение: новый верхнеуровневый namespace нужно добавить в `LAYERS` теста. Запуск: `php vendor/bin/phpunit tests/Unit/Architecture`.

## Путь запроса

```
Dota2ApiClient::matches() → MatchService::getMatch()/getMatches()
  → MatchSourceInterface = OpenDotaMatchSource   (endpoint "matches/{id}")
    → ApiGateway::request()     (ленивый: не ждёт заголовков, статус не проверяет)
      → RetryableHttpClient     (429/5xx/транспорт: до max_retries повторов, задержка 1с×2ⁿ, учитывает Retry-After)
        → HttpClient с base_uri, timeout, query api_key
    → ApiGateway::decode()      (404 → SourceRequestException "Object not found", иной не-200 → SourceRequestException,
                                 тело не JSON-массив → SourceDataException)
    → MatchExtractor::extract() (whitelist полей из огромного ответа OpenDota; нет ключа → null)
    → MatchMapper::toEntity()   (валидация, PlayerMapper/DraftMapper/LaneNormalizer; DomainException|TypeError|ValueError
                                 → SourceDataException). Роли — из position_est или UNKNOWN
  ← MatchDetail
  → MatchDetail::withResolvedRoles(RoleResolverInterface)  (в MatchService, для каждой стороны отдельно)
  ← MatchFetchResult

Dota2ApiClient::teams() = TeamSourceInterface = OpenDotaTeamSource   (без Application-слоя)
  → ApiGateway::get()           ("teams", "teams/{id}", "teams/{id}/matches|players|heroes")
  → Team*Mapper::toEntity()/toCollection()
```

`MatchService` — единственное место, где вызывается резолвер ролей; адаптер источника делает только HTTP и маппинг. `getMatch()` бросает `SourceRequestException` (HTTP), а ошибки данных (`SourceDataException`) и ролей (`DomainException`) возвращает в `MatchFetchResult`.

Команды возвращаются напрямую сущностями или списками сущностей. Ошибки — исключения `SourceRequestException` / `SourceDataException`.

## Пакетная загрузка

`MatchService::getMatches(list<MatchId> $ids, int $concurrency = 5)` — генератор `MatchFetchResult`. Default `concurrency` задан только в сервисе.

`OpenDotaMatchSource::getMatches()` держит окно до `$concurrency` ленивых `request()` и читает их через `ApiGateway::stream()`. Запросы стартуют параллельно; результаты отдаются по готовности, порядок не гарантирован. Ответ привязан к `MatchId` через `SplObjectStorage`, поэтому дубли id не теряются. Готовый ответ → `decode()` → extract → map.

Ошибка одного матча отдаётся **значением**, поток не прерывается: генератор источника отдаёт `MatchId => MatchDetail|SourceRequestException|SourceDataException` и сам исключений бандла не бросает. Это касается 404, 5xx, 429 после ретраев, обрыва и таймаута (`TransportExceptionInterface` из чанка → `SourceRequestException`), запроса, отвергнутого клиентом сразу, битого JSON и немаппящихся данных. Сервис оборачивает каждое значение в `MatchFetchResult` и резолвит роли у успешных.

## Исключения

```
Dota2ApiExceptionInterface (маркер, extends \Throwable) — его реализуют ВСЕ исключения бандла
├─ Exception\SourceRequestException       — транспорт, 404 ("Object not found"), иной не-200
├─ Exception\SourceDataException          — тело не JSON-массив (previous — \JsonException), данные не маппятся
│                                            (previous — DomainException / TypeError / ValueError)
├─ Exception\ClientConfigurationException — Dota2ApiClientBuilder::build() с source = stratz
└─ SharedKernel\Exception\DomainException — база всех Invalid*Exception и MatchPlayersException
```

Все — наследники `\Exception`. Порты объявляют `@throws SourceRequestException|SourceDataException`. `source: stratz` в DI — `InvalidConfigurationException` Symfony (не исключение бандла).

`MatchFetchResult::getError(): ?Dota2ApiExceptionInterface`:

| Ситуация | `getMatch()` | `getMatches()` |
|---|---|---|
| HTTP: транспорт, 404, 5xx, 429 после ретраев | бросает `SourceRequestException` | `SourceRequestException` в `getError()` |
| Битый JSON, данные не маппятся | `SourceDataException` в `getError()` | то же |
| Резолвер не разложил роли | `DomainException` в `getError()` (не обёрнут) | то же |

## DI

- `dota2_api.http_client` — **собственный приватный клиент бандла**: `HttpClient::create()` → `OpenDotaHttpClientFactory::create()` (фабрика ставится в `Dota2ApiExtension` по `source`). Глобальный `HttpClientInterface` / `http_client` приложения бандл не трогает.
- `ClientConfig` из параметров `dota2_api.*`.
- `ApiGateway` (+алиас `ApiGatewayInterface`) поверх `dota2_api.http_client`.
- `SourceFactoryInterface` → `OpenDotaSourceFactory` (autowire в `Dota2ApiExtension::loadOpenDota()`). `MatchSourceInterface` и `TeamSourceInterface` — сервисы-фабрики от него (`createMatchSource` / `createTeamSource`).
- `RoleResolverInterface` → `PrefilledRoleResolver` с inline `ProSceneRoleResolver` в `$inner`.
- `MatchService` — autowire (`MatchSourceInterface` + `RoleResolverInterface`).
- `Dota2ApiClient` — единственный публичный сервис, autowire (`MatchService` + `TeamSourceInterface`).

`Dota2ApiClientBuilder` собирает тот же граф руками для использования без Symfony: `OpenDotaHttpClientFactory` → `ApiGateway` → `OpenDotaSourceFactory`; `MatchService(createMatchSource(), PrefilledRoleResolver(ProSceneRoleResolver))`; `teams` — `createTeamSource()`. `source = stratz` → `ClientConfigurationException`.

```php
$client = (new Dota2ApiClientBuilder(
    new ClientConfig(baseUrl: 'https://api.opendota.com/api/', apiSource: ApiSource::OPENDOTA, timeout: 600),
    HttpClient::create(),
))->build();

$result = $client->matches()->getMatch(new MatchId(8794744726));
$match  = $result->getMatchDetail(); // ?MatchDetail, ошибка — в $result->getError()
```

## Конфигурация бандла

```yaml
dota2_api:
  source: opendota          # enum: opendota | stratz (stratz → ошибка сборки контейнера)
  timeout: 30
  max_retries: 3            # min 0
  opendota:
    base_url: 'https://api.opendota.com/api/'
    api_key: '%env(default::OPENDOTA_API_KEY)%'   # default null; уходит query-параметром api_key
  # base_url / api_key в корне — deprecated-алиасы opendota.*, корневое значение важнее (null → берётся opendota.*)
```

Параметры контейнера: `dota2_api.base_url`, `dota2_api.api_key` (для активного источника), `dota2_api.timeout`, `dota2_api.max_retries`, `dota2_api.source` (enum `ApiSource`).

## Новый источник (STRATZ)

1. `Infrastructure/Stratz/*`: HTTP-клиент/gateway (STRATZ — GraphQL + Bearer, `ApiGateway` заточен под REST GET), `Match/StratzMatchSource implements MatchSourceInterface`, `Team/StratzTeamSource implements TeamSourceInterface`, мапперы.
2. `StratzSourceFactory implements SourceFactoryInterface`.
3. Ветка `ApiSource::STRATZ` в `Dota2ApiExtension::load()` (секция конфига, фабрика `dota2_api.http_client`, `autowire(SourceFactoryInterface::class, StratzSourceFactory::class)`) и в `Dota2ApiClientBuilder::build()`.

OpenDota-код, порты, `MatchService` и DI-регистрации портов не меняются. Роли (`withResolvedRoles` + `PrefilledRoleResolver`) и обёртка ошибок в `MatchFetchResult` достаются новому источнику бесплатно из `MatchService` — источнику достаточно соблюсти контракт `MatchSourceInterface` (роли от источника или UNKNOWN, ошибки пачки — значением).
