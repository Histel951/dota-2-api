# Архитектура

## Слои и контексты (`src/`)

Код сгруппирован по источникам данных (`OpenDota`, позже `Stratz`): у каждого свой bounded context со своими Match и Team. Общее лежит в `SharedKernel`. Внутри контекста — по понятию, а не по типу класса.

```
Dota2ApiBundle.php            — класс бандла (пустой, вся настройка в DI Extension)
DependencyInjection/          — Configuration (дерево конфига dota2_api) + Dota2ApiExtension
Resources/config/             — services.php (Dota2ApiClient), opendota.php (граф OpenDota)
Client/                       — публичная точка входа: Dota2ApiClient (openDota()) + Dota2ApiClientBuilder (без Symfony)
  Config/                     — ClientConfig (base_url, api_key, timeout, max_retries, options) — общий для источников
Exception/                    — публичные исключения: Dota2ApiExceptionInterface (маркер), SourceRequestException,
                                SourceDataException
SharedKernel/                 — общие VO, нужные нескольким контекстам: MatchId, TeamId, TeamName, TeamTag, PlayerId,
                                PlayerName, HeroId, HeroName, Duration, MatchStartTime, Side, Counter, GPM, XPM, KDA,
                                WinrateStats, Cluster, LeagueId, LeagueName; Draft/, Region/;
                                Exception/ — DomainException (база доменных исключений) и общие Invalid*
Infrastructure/               — общее для всех источников
  Http/                       — HttpMethod, HttpStatusCode
  Mapping/                    — AbstractMapper
OpenDota/                     — источник OpenDota
  OpenDotaClient.php          — matches(): MatchService, teams(): TeamSourceInterface
  Match/
    Domain/                   — MatchDetail; Player/, Player/Stats/, Side/ (MatchResult, TeamSide), Role/, Exception/
    Application/             — MatchService (роли + обёртка ошибок), MatchFetchResult
    Port/                     — MatchSourceInterface
  Team/
    Domain/                   — Team, TeamStats; History/, Roster/, Heroes/; Exception/
    Port/                     — TeamSourceInterface
  Infrastructure/             — ApiGateway (+ApiGatewayInterface), OpenDotaHttpClientFactory (DEFAULT_BASE_URL),
                                OpenDotaSourceFactory
    Match/                    — OpenDotaMatchSource, MatchExtractor, MatchMapper, PlayerMapper, DraftMapper, LaneNormalizer
    Team/                     — OpenDotaTeamSource, TeamMapper, TeamMatchMapper, TeamPlayerMapper, TeamHeroMapper
```

Контексты источников независимы: класс из `OpenDota\…` не знает про `Stratz\…` и наоборот; общие понятия живут только в `SharedKernel`.

## Правила зависимостей

S — источник (`OpenDota`, `Stratz`), C — контекст (`Match`, `Team`).

- `SharedKernel` ни от чего не зависит (вне себя — только `Exception\Dota2ApiExceptionInterface`).
- `Exception` ни от чего не зависит.
- Общий `Infrastructure` → `SharedKernel`, `Exception`. От источников и `Client` не зависит.
- `S\C\Domain` → `SharedKernel`. **Match и Team, а также OpenDota и Stratz друг от друга не зависят.**
- `S\C\Port` → `S\C\Domain`, `SharedKernel`, `Exception`.
- `S\C\Application` → `S\C\Domain`, `S\C\Port`, `SharedKernel`, `Exception`.
- `S\Infrastructure` → `S\Match\Domain`, `S\Match\Port`, `S\Team\Domain`, `S\Team\Port`, `SharedKernel`, `Exception`, общий `Infrastructure`, `Client\Config`. Не зависит от своего `Application` и от `Client`.
- Клиент источника (`OpenDota\OpenDotaClient`) → `S\Match\Application`, `S\Match\Port`, `S\Team\Application`, `S\Team\Port`.
- `Client`, `Client/Config`, `DependencyInjection`, `Resources`, `Dota2ApiBundle` — composition root, без ограничений. От `S\Infrastructure` зависят только они.

Правила проверяет `tests/Unit/Architecture/LayerDependenciesTest.php`: парсит все файлы `src/` (`nikic/php-parser`, dev), собирает все ссылки на классы бандла — `use` и полные имена без `use` — и сверяет слой файла со слоем цели по белому списку. Слои для S ∈ {OpenDota, Stratz} × C ∈ {Match, Team} генерируются статически (`layers()`, `allowedTargets()`). Файл или цель в неизвестном слое — тоже нарушение: новый верхнеуровневый namespace нужно добавить в слои теста. Запуск: `php vendor/bin/phpunit tests/Unit/Architecture`.

## Путь запроса

```
Dota2ApiClient::openDota()->matches() → MatchService::getMatch()/getMatches()
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

Dota2ApiClient::openDota()->teams() = TeamSourceInterface = OpenDotaTeamSource   (без Application-слоя)
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
└─ SharedKernel\Exception\DomainException — база всех Invalid*Exception и MatchPlayersException
```

Все — наследники `\Exception`. Порты объявляют `@throws SourceRequestException|SourceDataException`.

`MatchFetchResult::getError(): ?Dota2ApiExceptionInterface`:

| Ситуация | `getMatch()` | `getMatches()` |
|---|---|---|
| HTTP: транспорт, 404, 5xx, 429 после ретраев | бросает `SourceRequestException` | `SourceRequestException` в `getError()` |
| Битый JSON, данные не маппятся | `SourceDataException` в `getError()` | то же |
| Резолвер не разложил роли | `DomainException` в `getError()` (не обёрнут) | то же |

## DI

`Dota2ApiExtension::load()` без ветвления: обрабатывает конфиг, ставит параметры и грузит `services.php` + `opendota.php`. Всё, кроме `Dota2ApiClient`, приватное.

- `services.php` — `Dota2ApiClient` (публичный, autowire `OpenDotaClient`).
- `opendota.php`:
  - `dota2_api.opendota.config` — `ClientConfig` из параметров `dota2_api.opendota.base_url|api_key`, `dota2_api.timeout|max_retries`.
  - `dota2_api.opendota.http_client` — **собственный приватный клиент бандла**: `HttpClient::create()` → `OpenDotaHttpClientFactory::create()`. Глобальный `HttpClientInterface` / `http_client` приложения бандл не трогает.
  - `ApiGateway` (+алиас `ApiGatewayInterface`) поверх `dota2_api.opendota.http_client`.
  - `OpenDotaSourceFactory` (без интерфейса); `OpenDota\Match\Port\MatchSourceInterface` и `OpenDota\Team\Port\TeamSourceInterface` — сервисы-фабрики от него (`createMatchSource` / `createTeamSource`).
  - `RoleResolverInterface` → `PrefilledRoleResolver` с inline `ProSceneRoleResolver` в `$inner`.
  - `MatchService` — autowire (`MatchSourceInterface` + `RoleResolverInterface`); `OpenDotaClient` — autowire (`MatchService` + `TeamSourceInterface`).

`Dota2ApiClientBuilder(HttpClientInterface $client, ?ClientConfig $openDota = null)` собирает тот же граф руками для использования без Symfony: `OpenDotaHttpClientFactory` → `ApiGateway` → `OpenDotaSourceFactory`; `OpenDotaClient(MatchService(createMatchSource(), PrefilledRoleResolver(ProSceneRoleResolver)), createTeamSource())`. Без конфига — `ClientConfig(OpenDotaHttpClientFactory::DEFAULT_BASE_URL)`.

```php
$client = (new Dota2ApiClientBuilder(
    HttpClient::create(),
    new ClientConfig(baseUrl: 'https://api.opendota.com/api/', timeout: 600),
))->build();

$result = $client->openDota()->matches()->getMatch(new MatchId(8794744726));
$match  = $result->getMatchDetail(); // ?MatchDetail, ошибка — в $result->getError()
```

## Конфигурация бандла

```yaml
dota2_api:
  timeout: 30
  max_retries: 3            # min 0
  opendota:
    base_url: 'https://api.opendota.com/api/'    # OpenDotaHttpClientFactory::DEFAULT_BASE_URL
    api_key: '%env(default::OPENDOTA_API_KEY)%'   # default null; уходит query-параметром api_key
```

Корневые `source`, `base_url`, `api_key` убраны: `InvalidConfigurationException` («Unrecognized option»). Параметры контейнера: `dota2_api.timeout`, `dota2_api.max_retries`, `dota2_api.opendota.base_url`, `dota2_api.opendota.api_key`.

## Новый источник (STRATZ)

1. `Infrastructure/Stratz/*`: HTTP-клиент/gateway (STRATZ — GraphQL + Bearer, `ApiGateway` заточен под REST GET), `Match/StratzMatchSource implements MatchSourceInterface`, `Team/StratzTeamSource implements TeamSourceInterface`, мапперы.
2. `StratzSourceFactory implements SourceFactoryInterface`.
3. Ветка `ApiSource::STRATZ` в `Dota2ApiExtension::load()` (секция конфига, фабрика `dota2_api.http_client`, `autowire(SourceFactoryInterface::class, StratzSourceFactory::class)`) и в `Dota2ApiClientBuilder::build()`.

OpenDota-код, порты, `MatchService` и DI-регистрации портов не меняются. Роли (`withResolvedRoles` + `PrefilledRoleResolver`) и обёртка ошибок в `MatchFetchResult` достаются новому источнику бесплатно из `MatchService` — источнику достаточно соблюсти контракт `MatchSourceInterface` (роли от источника или UNKNOWN, ошибки пачки — значением).
