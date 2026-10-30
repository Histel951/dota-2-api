# Известные проблемы и технический долг

Актуально на 2026-10-30, после выделения источников в bounded contexts и добавления STRATZ (ветка `feature/stratz-source`). Перед тем как опираться на пункт, стоит перепроверить его по коду.

## Данные и домен — OpenDota

- **Анонимные игроки.** Если у игрока скрыт профиль (`account_id = null`, нет `personaname`), то `PlayerId` и `PlayerName` не создаются, и весь матч уходит в `SourceDataException`. Для про-матчей это редкость, но в пабах встречается. У STRATZ так же (`steamAccountId = null`).
- **Нет мида → ошибка ролей** — только если у стороны нет валидного `position_est` (иначе эвристика не вызывается). `ProSceneRoleResolver` бросает `DomainException`, если на миду 0 или больше 3 игроков, и матч приходит без `MatchDetail` с этим `DomainException` в `MatchFetchResult::getError()`. Зафиксировано тестами `ProSceneRoleResolverTest::test_resolveWithoutMiddlePlayerFails` и `MatchMapperPositionEstTest::test_noMiddleLayoutWithoutPositionEstFailsWithDomainException`.
- **2 игрока на миду и 3 на safe** (только без валидного `position_est`). Мидер-саппорт получает HARD_SUPPORT, и тройка на safe тоже раздаёт HARD_SUPPORT и SUPPORT. В итоге у команды две роли HARD_SUPPORT.
- В `ProSceneRoleResolver` есть неиспользуемые приватные методы `assertLaneComposition()` и `assertLaneCount()`.
- **Нераспарсенные матчи не маппятся.** У публичных нераспарсенных матчей (`version = null`) нет `lane`, `position_est`, большинства счётчиков, а у игроков со скрытым профилем `account_id = null` и `hero_id = 0`. Маппер падает с `SourceDataException` (`Hero ID must be at least 1, 0 given`), проверено 2026-10-30 на `publicMatches`. Про-матчи распарсены всегда.

## Данные и домен — общее (SharedKernel)

- **`KDA::getCoefficient()`** считает `kills + assists / deaths` (неверные скобки), а при `deaths = 0` — деление на ноль. Метод переехал в `SharedKernel\KDA` и теперь доступен игрокам обоих источников; при починке учесть обоих.

## STRATZ

- **Окно параллельных запросов скопировано из OpenDota.** `StratzMatchSource::getMatches()` / `fillWindow()` повторяют цикл `OpenDotaMatchSource` (очередь, `SplObjectStorage`, `stream()`, проверка статуса на `isFirst()`, `TransportExceptionInterface` из чанка). Общий помощник в `Infrastructure/` — при третьем потребителе (источники не могут ссылаться друг на друга).
- **Один матч на запрос.** `MatchQuery::BATCH_SIZE = 1`, потому что STRATZ пересобирает `playbackData` не более чем для одного незакэшированного матча за запрос. Пачка некэшированных матчей грузится медленно: каждый запрос ждёт пересборку реплея, ускоряет только `$concurrency`. Механизм алиасов (`m0`, `m1`, …) остался и проверен тестами — `BATCH_SIZE` можно поднять, если нужен будет режим без `playbackData`.
- **Лимит 150 запросов/мин без троттлинга.** Бандл не ограничивает частоту: большая пачка `getMatches()` (при `BATCH_SIZE = 1` — запрос на матч) упрётся в 429; `RetryableHttpClient` повторит до `max_retries` раз, затем матч придёт с `SourceRequestException` в `getError()`. Потребителю пока нужно дозировать пачки самому.
- **groupBy-окно.** `getPlayersByTeam()` / `getHeroesByTeam()` считают matches/wins по окну последних матчей STRATZ (~100), а не за всю историю команды (у OpenDota — за всю). Цифры двух источников несопоставимы.
- **Имя команды в матче — текущее**, а не на момент матча (`radiantTeam.name`). У переименованных команд старые матчи показывают новое имя.
- **Нераспарсенные матчи.** Свежие (≲ 4 недели) и часть старых матчей STRATZ не распарсил: `ParsedStats`, `Position`, `Draft` = `null`, `Lane::UNKNOWN`. Это не ошибка, но потребителю нужно проверять `isParsed()`.

## Инфраструктура

- `HttpStatusCode::TOO_MANY_REQUESTS` и `INTERNAL_SERVER_ERROR` не используются.
- `composer audit` показывает 2 advisory в зависимостях (vendor) — нужно посмотреть.

## У потребителя (`dota2-analytical-service`)

- **Бандл 2.0 ломает его импорты.** Совместимость сознательно не сохранялась: классы переехали (`Domain\*` → `OpenDota\Match\*` / `OpenDota\Team\*` / `SharedKernel\*`, `Infrastructure\Exceptions\*` → `Exception\*`), порты переименованы (`MatchSourceInterface`, `TeamSourceInterface`), `getError()` — `?Dota2ApiExceptionInterface`, вместо `$client->matches()` / `teams()` — `$client->openDota()->matches()` / `teams()`. Потребитель подключает `dev-main` (теперь `2.0-dev`) и автовайрит `Dota2ApiClient` — при обновлении править `use`, вызовы клиента и, если он переопределяет сервисы бандла, их id. `packages/test` ломается так же (`new Dota2ApiClientBuilder($http, ?ClientConfig $openDota, ?ClientConfig $stratz)`, `ClientConfig` без `ApiSource`).
- **Конфиг в старом формате больше не собирается.** Корневые `dota2_api.base_url` / `api_key` (и `source`) удалены — контейнер падает с `InvalidConfigurationException` («Unrecognized option»). Перед обновлением перейти на `dota2_api.opendota.{base_url, api_key}`; для STRATZ — `dota2_api.stratz.token`.
- `FetchMatchFromApiHandler` не читает `getError()`, а при `null` бросает общее «Match detail is null». Реальная причина (например, ошибка ролей) теряется в логах.
- В `config/services.yaml` есть ссылки на `Domain\Services\RegionResolverInterface` и `Infrastructure\Services\RegionResolver`. В бандле таких классов нет.

## Исправлено

- **Источники как bounded contexts + STRATZ (2026-10-30).** `OpenDota/` и `Stratz/` — отдельные модели, общий словарь в `SharedKernel`; переключатель `source`, `SourceFactoryInterface`, `ApiSource`, `ClientConfigurationException` и корневые `base_url`/`api_key` удалены. STRATZ реализован: матчи (`getMatch`, `getMatches`) и команды (4 метода), без подстановки нулей вместо недоступных показателей.
- **Рефакторинг по контекстам DDD (2026-10-30).** HTTP-ошибка одного матча в `getMatches()` больше не роняет генератор — она приходит `SourceRequestException` в `getError()`, поток идёт дальше (включая обрыв и таймаут чанка). `getMatches()` параллелится с первого запроса: `ApiGateway::request()` ленивый, статус проверяет `decode()`. `MatchExtractor` работает с декодированным массивом и не даёт warning на отсутствующих ключах (нет ключа → `null`, обязательность проверяет `MatchMapper`); битый JSON → `SourceDataException`. Все исключения реализуют `Dota2ApiExceptionInterface`. Подробности — architecture.md.
- **Проверка полей источника (2026-10-30).** `getPlayersByTeam()` падал на бывших игроках (`name` / `is_current_team_member` = `null`). `getTeams()` падал на командах с пустым `tag`. Удалена пустая фикстура `team_responses.json`.
- **Рефакторинг 2026-10-11** (по [refactoring-plan.md](refactoring-plan.md)). `dd()` в `getMatch()`; `TeamName(null)` / `TeamId(null)`; лесные линии и `lane_role`; ретраи 429/5xx; тип таймаута; подмена `http_client` приложения; двойной граф DI и двойная регистрация `RoleResolverInterface`; зависимость домена от инфраструктуры (`MatchResult`); `ApiGateway` на конкретном классе; `PlayerProviderInterface` и `ProviderException` удалены; `toCollection()`, возвращавший `[]`, удалён; схема `phpunit.xml.dist`; `strict_types` везде.
