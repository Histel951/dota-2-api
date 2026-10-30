# Архитектура

## Слои и контексты (`src/`)

Каждый источник данных — свой bounded context со своей доменной моделью: `OpenDota` и `Stratz`. Модели не смешиваются: у каждого источника свои `Match` и `Team` с тем, что этот источник реально отдаёт. Общее лежит в `SharedKernel` (словарь игры) и в общем `Infrastructure` (без привязки к источнику). Внутри контекста код разложен по понятию, а не по типу класса.

```
Dota2ApiBundle.php            — класс бандла (пустой, вся настройка в DI Extension)
DependencyInjection/          — Configuration (дерево конфига dota2_api) + Dota2ApiExtension
Resources/config/             — services.php (Dota2ApiClient), opendota.php (граф OpenDota), stratz.php (граф STRATZ)
Client/                       — публичная точка входа: Dota2ApiClient (openDota(), stratz()) + Dota2ApiClientBuilder (без Symfony)
  Config/                     — ClientConfig (baseUrl, apiKey, timeout, maxRetries, options) — общий для источников
Exception/                    — публичные исключения: Dota2ApiExceptionInterface (маркер), SourceRequestException,
                                SourceDataException
SharedKernel/                 — словарь игры, одинаковый по смыслу у всех источников: MatchId, MatchStartTime, TeamId,
                                TeamName, TeamTag, PlayerId, PlayerName, HeroId, HeroName, Duration, Side, Counter, KDA,
                                GPM, XPM, WinrateStats, Cluster, LeagueId, LeagueName; Draft/ (Draft, DraftDecision,
                                DraftOrder), Region/ (Region, RegionType);
                                Exception/ — DomainException (база доменных исключений) и Invalid* этих VO
Infrastructure/               — общее для всех источников, ничего не знает об источниках
  Http/                       — HttpMethod (GET, POST), HttpStatusCode
  Mapping/                    — AbstractMapper
OpenDota/                     — источник OpenDota (REST)
  OpenDotaClient.php          — matches(): MatchService, teams(): TeamSourceInterface
  Match/
    Domain/                   — MatchDetail; Player/, Player/Stats/, Side/ (MatchResult, TeamSide), Role/, Exception/
    Application/              — MatchService (роли + обёртка ошибок), MatchFetchResult
    Port/                     — MatchSourceInterface
  Team/
    Domain/                   — Team, TeamStats; History/, Roster/, Heroes/; Exception/
    Port/                     — TeamSourceInterface
  Infrastructure/             — ApiGateway (+ApiGatewayInterface), OpenDotaHttpClientFactory (DEFAULT_BASE_URL),
                                OpenDotaSourceFactory
    Match/                    — OpenDotaMatchSource, MatchExtractor, MatchMapper, PlayerMapper, DraftMapper, LaneNormalizer
    Team/                     — OpenDotaTeamSource, TeamMapper, TeamMatchMapper, TeamPlayerMapper, TeamHeroMapper
Stratz/                       — источник STRATZ (GraphQL)
  StratzClient.php            — matches(): MatchService, teams(): TeamSourceInterface
  Match/
    Domain/                   — MatchDetail, TeamSide; Player/ (MatchPlayer, Identity, Lane, Position),
                                Player/Stats/ (Economy, Damage, Farm, ParsedStats, StunDuration, счётчики); Exception/
    Application/              — MatchService (обёртка ошибок, без резолвера ролей), MatchFetchResult
    Port/                     — MatchSourceInterface
  Team/
    Domain/                   — Team; History/ (TeamMatch, Score), Roster/ (TeamPlayer), Heroes/ (TeamHero); Exception/
    Port/                     — TeamSourceInterface
  Infrastructure/             — GraphQlGateway, GraphQlResult, StratzHttpClientFactory (DEFAULT_BASE_URL), StratzSourceFactory
    Match/                    — StratzMatchSource, MatchQuery, MatchMapper, PlayerMapper, DraftMapper, LaneMapper
    Team/                     — StratzTeamSource, TeamQuery, TeamMapper, TeamMatchMapper, TeamPlayerMapper, TeamHeroMapper
```

Одноимённые классы в двух источниках (`MatchService`, `MatchDetail`, `TeamSourceInterface`, мапперы) — норма: источник задаёт namespace. Где оба нужны в одном файле (Builder, DI, тесты), используется `use … as` (`StratzMatchService`, `StratzMatchSourceInterface`).

## Правила зависимостей

S — источник (`OpenDota`, `Stratz`), C — контекст (`Match`, `Team`).

- `SharedKernel` ни от чего не зависит (вне себя — только `Exception\Dota2ApiExceptionInterface`).
- `Exception` ни от чего не зависит.
- Общий `Infrastructure` → `SharedKernel`, `Exception`. От источников и `Client` не зависит.
- `S\C\Domain` → `SharedKernel`.
- `S\C\Port` → `S\C\Domain`, `SharedKernel`, `Exception`.
- `S\C\Application` → `S\C\Domain`, `S\C\Port`, `SharedKernel`, `Exception`.
- `S\Infrastructure` → `S\Match\Domain`, `S\Match\Port`, `S\Team\Domain`, `S\Team\Port`, `SharedKernel`, `Exception`, общий `Infrastructure`, `Client\Config`. Не зависит от своего `Application` и от `Client`.
- Клиент источника (`OpenDota\OpenDotaClient`, `Stratz\StratzClient`) → `S\Match\Application`, `S\Match\Port`, `S\Team\Application`, `S\Team\Port`.
- `Client`, `Client\Config`, `DependencyInjection`, `Resources`, `Dota2ApiBundle` — composition root, без ограничений. От `S\Infrastructure` зависят только они.
- **Запрещено**: `OpenDota\*` ↔ `Stratz\*` (в обе стороны, на любом слое); внутри источника `S\Match\*` ↔ `S\Team\*`; всё, что не перечислено выше.

Правила проверяет `tests/Unit/Architecture/LayerDependenciesTest.php`: парсит все файлы `src/` (`nikic/php-parser`, dev), собирает все ссылки на классы бандла — `use` и полные имена без `use` — и сверяет слой файла со слоем цели по белому списку. Слои строятся статически из констант `SOURCES` (`OpenDota`, `Stratz`), `CONTEXTS` (`Match`, `Team`) и `COMMON_LAYERS` в методах `layers()` и `allowedTargets()`; слой файла — самый длинный подходящий префикс. Файл или цель в неизвестном слое — тоже нарушение: новый верхнеуровневый namespace добавляется в `COMMON_LAYERS`, новый источник — в `SOURCES`. Мутанты в том же тесте фиксируют запреты (`test_detectsOpenDotaDependingOnStratz`, `test_detectsStratzDependingOnOpenDota`, `test_detectsMatchDependingOnTeam`, …). Запуск: `php vendor/bin/phpunit tests/Unit/Architecture`.

## Путь запроса — OpenDota

```
Dota2ApiClient::openDota()->matches() → OpenDota\Match\Application\MatchService::getMatch()/getMatches()
  → MatchSourceInterface = OpenDotaMatchSource   (endpoint "matches/{id}")
    → ApiGateway::request()     (ленивый GET: не ждёт заголовков, статус не проверяет)
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

Dota2ApiClient::openDota()->teams() = OpenDota\Team\Port\TeamSourceInterface = OpenDotaTeamSource   (без Application-слоя)
  → ApiGateway::get()           ("teams", "teams/{id}", "teams/{id}/matches|players|heroes")
  → Team*Mapper::toEntity()/toCollection()
```

`OpenDota\Match\Application\MatchService` — единственное место, где вызывается резолвер ролей; адаптер источника делает только HTTP и маппинг. `getMatch()` бросает `SourceRequestException` (HTTP), а ошибки данных (`SourceDataException`) и ролей (`DomainException`) возвращает в `MatchFetchResult`.

## Путь запроса — STRATZ

```
Dota2ApiClient::stratz()->matches() → Stratz\Match\Application\MatchService::getMatch()/getMatches()
  → Stratz\Match\Port\MatchSourceInterface = StratzMatchSource
    → MatchQuery::build([id])    (query Matches($m0: Long!) { m0: match(id: $m0) { ...MatchFields } } + fragment FIELDS)
    → GraphQlGateway::request()  (ленивый POST на base_uri, json {query, variables})
      → RetryableHttpClient      (GenericRetryStrategy: 0, 423, 425, 429, 500, 502, 503, 504, 507, 510 — POST тоже повторяется)
        → HttpClient с base_uri = полный URL эндпоинта, timeout, User-Agent: STRATZ_API, auth_bearer = token
    → GraphQlGateway::decode()   (не-200 → SourceRequestException "API returned unexpected status code N: <errors STRATZ>";
                                  не JSON → SourceDataException; data = null + errors → SourceRequestException с текстами;
                                  ни data, ни errors / data не объект → SourceDataException)
    ← GraphQlResult(data, errors)
    → GraphQlResult::field('m0') (null → SourceRequestException: текст error с path[0] = m0, иначе "Object not found";
                                  нет поля / не объект → SourceDataException)
    → MatchMapper::toEntity()    (PlayerMapper/LaneMapper/DraftMapper; DomainException|TypeError|ValueError → SourceDataException)
  ← MatchDetail  (позиции — от STRATZ, резолвера ролей нет)
  ← MatchFetchResult

Dota2ApiClient::stratz()->teams() = Stratz\Team\Port\TeamSourceInterface = StratzTeamSource   (без Application-слоя)
  → GraphQlGateway::query(TeamQuery::TEAM|MATCHES|PLAYERS|HEROES, ['teamId' => int])
  → GraphQlResult::field('team') (+ field('constants') для имён героев)
  → Team*Mapper::toEntity()      (в строки матчей и игроков источник подмешивает teamId, в строки героев — displayName)
```

Частичный ответ GraphQL (есть и `data`, и `errors`) — не ошибка `decode()`: ошибка всплывает только у поля, которое пришло `null`. Ошибки запроса (неизвестное поле и т.п.) STRATZ отдаёт со статусом 400 — их тексты `decode()` дописывает в сообщение.

`Stratz\Match\Application\MatchService(MatchSourceInterface $source)` повторяет семантику OpenDota-сервиса, но без резолвера: `getMatch()` бросает `SourceRequestException`, `SourceDataException` возвращает в `MatchFetchResult`.

Команды обоих источников возвращаются напрямую сущностями или списками сущностей. Ошибки — исключения `SourceRequestException` / `SourceDataException`.

## Пакетная загрузка

`MatchService::getMatches(list<MatchId> $ids, int $concurrency = 5)` у обоих источников — генератор `MatchFetchResult`. Default `concurrency` задан только в сервисах.

**OpenDota.** `OpenDotaMatchSource::getMatches()` держит окно до `$concurrency` ленивых `request()` и читает их через `ApiGateway::stream()`. Запросы стартуют параллельно; результаты отдаются по готовности, порядок не гарантирован. Ответ привязан к `MatchId` через `SplObjectStorage`, поэтому дубли id не теряются. Готовый ответ → `decode()` → extract → map. Сервис резолвит роли у успешных.

**STRATZ.** `StratzMatchSource::getMatches()` режет id на пачки по `MatchQuery::BATCH_SIZE` (алиасы `m0`, `m1`, … в одном GraphQL-запросе) и держит окно до `$concurrency` HTTP-запросов через `GraphQlGateway::stream()` (`SplQueue` пачек + `SplObjectStorage<ResponseInterface, list<MatchId>>`). Сейчас `BATCH_SIZE = 1`: STRATZ пересобирает `playbackData` из реплея не более чем для одного незакэшированного матча за запрос, поэтому один матч — один запрос, а параллельность даёт только окно. Механизм алиасов оставлен: увеличение `BATCH_SIZE` не требует других правок. Ошибки по пачке:

- запрос отвергнут клиентом сразу, обрыв/таймаут (`TransportExceptionInterface` из чанка), ошибка `decode()` — одно и то же исключение значением **на каждый id пачки**;
- `field()` (алиас `null` → «Object not found») и маппинг — только у своего id (по индексу алиаса, поэтому дубли не теряются).

Общее для обоих: ошибка одного матча отдаётся **значением**, поток не прерывается. Генератор источника отдаёт `MatchId => MatchDetail|SourceRequestException|SourceDataException` и сам исключений бандла не бросает. Это касается 404 (OpenDota) / пустого алиаса (STRATZ), 5xx, 429 после ретраев, обрыва и таймаута, запроса, отвергнутого клиентом сразу, битого JSON и немаппящихся данных. Сервис оборачивает каждое значение в `MatchFetchResult`.

## Исключения

```
Dota2ApiExceptionInterface (маркер, extends \Throwable) — его реализуют ВСЕ исключения бандла
├─ Exception\SourceRequestException       — транспорт, 404 / пустое поле GraphQL ("Object not found"), иной не-200,
│                                            ответ GraphQL с errors без data
├─ Exception\SourceDataException          — тело не JSON (previous — \JsonException / DecodingException), данные не маппятся
│                                            (previous — DomainException / TypeError / ValueError)
└─ SharedKernel\Exception\DomainException — база всех Invalid*Exception и исключений контекстов
                                             (OpenDota: MatchPlayersException, InvalidTeamStatsException;
                                              Stratz: InvalidMatchSideException, InvalidStunDurationException, InvalidScoreException)
```

Все — наследники `\Exception`. Порты объявляют `@throws SourceRequestException|SourceDataException`.

`MatchFetchResult::getError(): ?Dota2ApiExceptionInterface` (оба источника):

| Ситуация | `getMatch()` | `getMatches()` |
|---|---|---|
| HTTP: транспорт, 404 / матча нет у STRATZ, 403 (нет токена), 5xx, 429 после ретраев, GraphQL `errors` | бросает `SourceRequestException` | `SourceRequestException` в `getError()` |
| Битый JSON, данные не маппятся | `SourceDataException` в `getError()` | то же |
| Резолвер не разложил роли (только OpenDota) | `DomainException` в `getError()` (не обёрнут) | то же |

## DI

`Dota2ApiExtension::load()` без ветвления: обрабатывает конфиг, ставит параметры `dota2_api.timeout`, `dota2_api.max_retries`, `dota2_api.opendota.base_url|api_key`, `dota2_api.stratz.base_url|token` и грузит три файла. Оба источника регистрируются всегда. Всё, кроме `Dota2ApiClient`, приватное.

- `services.php` — `Dota2ApiClient` (публичный, autowire `OpenDotaClient` + `StratzClient`).
- `opendota.php`:
  - `dota2_api.opendota.config` — `ClientConfig` из `dota2_api.opendota.base_url|api_key`, `dota2_api.timeout|max_retries`.
  - `dota2_api.opendota.http_client` — **собственный приватный клиент**: inline `HttpClient::create()` → `OpenDotaHttpClientFactory::create()`. Глобальный `HttpClientInterface` / `http_client` приложения бандл не трогает.
  - `ApiGateway` (+алиас `ApiGatewayInterface`) поверх `dota2_api.opendota.http_client`.
  - `OpenDotaSourceFactory`; `OpenDota\Match\Port\MatchSourceInterface` и `OpenDota\Team\Port\TeamSourceInterface` — сервисы-фабрики от него (`createMatchSource` / `createTeamSource`).
  - `RoleResolverInterface` → `PrefilledRoleResolver` с inline `ProSceneRoleResolver` в `$inner`.
  - `OpenDota\Match\Application\MatchService` и `OpenDotaClient` — autowire.
- `stratz.php`:
  - `dota2_api.stratz.config` — `ClientConfig` из `dota2_api.stratz.base_url`, `$apiKey = dota2_api.stratz.token`, `dota2_api.timeout|max_retries`.
  - `dota2_api.stratz.http_client` — inline `HttpClient::create()` → `StratzHttpClientFactory::create()`.
  - `GraphQlGateway` поверх `dota2_api.stratz.http_client`.
  - `StratzSourceFactory`; `Stratz\Match\Port\MatchSourceInterface` и `Stratz\Team\Port\TeamSourceInterface` — сервисы-фабрики от него.
  - `Stratz\Match\Application\MatchService` и `StratzClient` — autowire.

Id сервисов — FQCN, поэтому одноимённые классы источников не конфликтуют.

## Builder

`new Dota2ApiClientBuilder(HttpClientInterface $client, ?ClientConfig $openDota = null, ?ClientConfig $stratz = null)` собирает тот же граф руками для использования без Symfony:

- OpenDota: `OpenDotaHttpClientFactory::create()` → `ApiGateway` → `OpenDotaSourceFactory` → `OpenDotaClient(MatchService(createMatchSource(), PrefilledRoleResolver(ProSceneRoleResolver)), createTeamSource())`. Без конфига — `ClientConfig(OpenDotaHttpClientFactory::DEFAULT_BASE_URL)`.
- STRATZ: `StratzHttpClientFactory::create()` → `GraphQlGateway` → `StratzSourceFactory` → `StratzClient(MatchService(createMatchSource()), createTeamSource())`. Без конфига — `ClientConfig(StratzHttpClientFactory::DEFAULT_BASE_URL)` без токена: клиент собирается, но запросы получат 403 → `SourceRequestException`.

`ClientConfig::apiKey` — у OpenDota query-параметр `api_key`, у STRATZ Bearer-токен.

```php
$client = (new Dota2ApiClientBuilder(
    HttpClient::create(),
    openDota: new ClientConfig(baseUrl: OpenDotaHttpClientFactory::DEFAULT_BASE_URL, timeout: 600),
    stratz: new ClientConfig(baseUrl: StratzHttpClientFactory::DEFAULT_BASE_URL, apiKey: getenv('STRATZ_TOKEN') ?: null),
))->build();

$result = $client->openDota()->matches()->getMatch(new MatchId(8794744726));
$match  = $result->getMatchDetail(); // ?OpenDota\Match\Domain\MatchDetail, ошибка — в $result->getError()

$stratz = $client->stratz()->matches()->getMatch(new MatchId(9026055036))->getMatchDetail(); // ?Stratz\Match\Domain\MatchDetail
```

Примеры: `examples/fetch_match.php` (OpenDota), `examples/fetch_stratz_match.php` (STRATZ).

## Конфигурация бандла

```yaml
dota2_api:
  timeout: 30               # общий для источников
  max_retries: 3            # min 0, общий для источников
  opendota:
    base_url: 'https://api.opendota.com/api/'    # OpenDotaHttpClientFactory::DEFAULT_BASE_URL
    api_key: '%env(default::OPENDOTA_API_KEY)%'   # default null; уходит query-параметром api_key
  stratz:
    base_url: 'https://api.stratz.com/graphql'   # StratzHttpClientFactory::DEFAULT_BASE_URL
    token: '%env(default::STRATZ_TOKEN)%'         # default null; уходит Bearer-токеном, без него STRATZ отвечает 403
```

Обе секции — `addDefaultsIfNotSet()`, их можно не указывать. Корневые `source`, `base_url`, `api_key` удалены: `InvalidConfigurationException` («Unrecognized option»). Выбора источника нет — оба доступны одновременно через `openDota()` и `stratz()`.

## Как добавить третий источник

На примере источника `Foo`:

1. `src/Foo/{Match,Team}/{Domain,Port}` (+ `Application`, если нужна оркестрация) — своя модель того, что источник реально отдаёт. Из `SharedKernel` берётся только то, что одинаково по смыслу (id, имена, длительность, регион Valve, сторона, KDA/GPM/XPM, драфт). Линия, роль, статистика игрока, команда — свои.
2. `src/Foo/Infrastructure/` — транспорт (свой gateway и `FooHttpClientFactory` с `DEFAULT_BASE_URL`, если ни `ApiGateway`, ни `GraphQlGateway` не подходят — они принадлежат своим источникам и переиспользовать их нельзя), `FooSourceFactory` с `create*Source()`, адаптеры `Match/FooMatchSource`, `Team/FooTeamSource`, мапперы на `Infrastructure\Mapping\AbstractMapper`.
3. `src/Foo/FooClient.php` — `matches()` / `teams()`.
4. `LayerDependenciesTest::SOURCES` += `'Foo'` (слои и запреты на `OpenDota`/`Stratz` появятся автоматически).
5. Конфиг: секция `foo` в `Configuration`, параметры `dota2_api.foo.*` в `Dota2ApiExtension::load()`, `Resources/config/foo.php` по образцу `stratz.php` и `$loader->load('foo.php')`.
6. `Dota2ApiClient` += `FooClient $foo` и `foo()`; `Dota2ApiClientBuilder` += `?ClientConfig $foo = null` и `buildFoo()`. Это ломающее изменение для тех, кто создаёт `Dota2ApiClient` руками (DI и Builder — нет).
7. Фикстуры `tests/Fixtures/foo/`, скрипт `tools/refresh-foo-fixtures.php`, тесты `tests/Unit/Foo/**`; docs — раздел в `api-coverage.md` и `domain.md`.

Код OpenDota и STRATZ при этом не меняется.

Окно параллельных запросов сейчас продублировано в `OpenDotaMatchSource` и `StratzMatchSource` (known-issues.md); у третьего потребителя его стоит вынести в общий `Infrastructure`.
