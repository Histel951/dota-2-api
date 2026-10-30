# Архитектура

## Слои (`src/`)

```
Dota2ApiBundle.php            — класс бандла (пустой, вся настройка в DI Extension)
DependencyInjection/          — Configuration (дерево конфига dota2_api) + Dota2ApiExtension
Resources/config/services.php — регистрация сервисов
Client/                       — публичная точка входа: Dota2ApiClient + Dota2ApiClientBuilder (без Symfony)
Domain/
  Common/ValueObjects, Enums, Exceptions — общие VO и DomainException (база всех доменных исключений)
  Entities/Match|Team|TeamMatch|TeamPlayer|TeamHero — сущности и их VO
  Entities/Match/Services/PrefilledRoleResolver — роли из position_est, иначе делегирует
  Entities/Match/Services/ProSceneRoleResolver — эвристика ролей 1–5 (запасной вариант)
  Providers/*ProviderInterface, Providers/Results/MatchFetchResult — контракты провайдеров (порты)
  Services/RoleResolverInterface
Infrastructure/               — общее для всех источников
  Http/       — ApiGateway (+интерфейс), ConfigurationHttpClient, enums (ApiSource, HttpMethod, HttpStatusCode)
  Mapper/     — AbstractMapper
  Provider/Contracts — ProviderFactory, ExtractorInterface
  Exceptions/ — ApiGatewayException, MappingException, Dota2ApiClientBuilderException
  OpenDota/   — всё, что специфично для OpenDota
    Http/OpenDotaHttpClientFactory — base_uri, timeout, api_key, RetryableHttpClient
    Provider/  — OpenDotaMatchProvider, OpenDotaTeamProvider, OpenDotaProviderFactory
    Mapper/    — *OpenDotaMapper (сырой массив → доменная сущность)
    Extractor/ — MatchExtractor (whitelist полей ответа матча)
```

Слой Domain не импортирует Infrastructure. Новый источник добавляется так: `Infrastructure/Stratz/*` + ветка в `Dota2ApiExtension::load()` и в `Dota2ApiClientBuilder::build()`.

## Путь запроса

```
Dota2ApiClient::matches()/teams()
  → OpenDota*Provider             (формирует endpoint, напр. "matches/{id}", "teams/{id}/players")
    → ApiGateway                  (request/get/stream; не-200 → ApiGatewayException, 404 → "Object not found")
      → RetryableHttpClient       (429/5xx/транспорт: до max_retries повторов, задержка 1с×2ⁿ, учитывает Retry-After)
        → HttpClient с base_uri, timeout, query api_key
  ← JSON
  → [только матчи] MatchExtractor (whitelist полей из огромного ответа OpenDota)
  → *OpenDotaMapper::toEntity()   (валидация, сборка VO и сущностей; любые ошибки данных → MappingException)
  → [только матчи] RoleResolverInterface::resolve() для каждой стороны
```

Матчи возвращаются через обёртку `Domain\Providers\Results\MatchFetchResult` (`matchId`, `?MatchDetail`, `?Throwable`). Поэтому при пакетной загрузке ошибка одного матча не роняет весь поток. Ошибки HTTP (`ApiGatewayException`) из `getMatch()` по-прежнему бросаются.
`getMatches(MatchId[] $ids, int $concurrency = 5)` — генератор. Он держит до `$concurrency` параллельных запросов через `HttpClient::stream()` и отдаёт результаты по мере готовности.

Команды возвращаются напрямую сущностями или массивами сущностей. Ошибки здесь — исключения `ApiGatewayException` / `MappingException`.

## DI

- `dota2_api.http_client` — **собственный приватный клиент бандла**: `HttpClient::create()` → `OpenDotaHttpClientFactory::create()`. Глобальный `HttpClientInterface` / `http_client` приложения бандл не трогает.
- `ApiGateway` (+алиас `ApiGatewayInterface`) поверх `dota2_api.http_client`.
- `ProviderFactory` → `OpenDotaProviderFactory` (по `source`). `MatchesProviderInterface` и `TeamProviderInterface` — сервисы-фабрики от него, автовайрятся по интерфейсу.
- `RoleResolverInterface` → `PrefilledRoleResolver` с inline `ProSceneRoleResolver` в `$inner`, передаётся в `OpenDotaProviderFactory`. Значение по умолчанию в фабрике (и, значит, в `Dota2ApiClientBuilder`) — тот же декоратор.
- `Dota2ApiClient` — единственный публичный сервис, автовайрится из интерфейсов провайдеров.

`Dota2ApiClientBuilder` собирает тот же граф руками (`OpenDotaHttpClientFactory` → `ApiGateway` → `OpenDotaProviderFactory`) для использования без Symfony:

```php
$client = (new Dota2ApiClientBuilder(
    new ConfigurationHttpClient(baseUrl: 'https://api.opendota.com/api/', apiSource: ApiSource::OPENDOTA, timeout: 600),
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
  max_retries: 3
  opendota:
    base_url: 'https://api.opendota.com/api/'
    api_key: '%env(default::OPENDOTA_API_KEY)%'   # default null; уходит query-параметром api_key
  # base_url / api_key в корне — deprecated-алиасы opendota.*, корневое значение важнее (null → берётся opendota.*)
```

Параметры контейнера: `dota2_api.base_url`, `dota2_api.api_key` (для активного источника), `dota2_api.timeout`, `dota2_api.max_retries`, `dota2_api.source` (enum `ApiSource`).
