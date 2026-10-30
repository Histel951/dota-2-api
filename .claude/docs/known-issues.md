# Известные проблемы и технический долг

Актуально на 2026-10-30, после рефакторинга по контекстам DDD (ветка `refactoring/ddd-contexts`). Перед тем как опираться на пункт, стоит перепроверить его по коду.

## Данные и домен

- **Анонимные игроки.** Если у игрока скрыт профиль (`account_id = null`, нет `personaname`), то `PlayerId` и `PlayerName` не создаются, и весь матч уходит в `SourceDataException`. Для про-матчей это редкость, но в пабах встречается.
- **Нет мида → ошибка ролей** — только если у стороны нет валидного `position_est` (иначе эвристика не вызывается). `ProSceneRoleResolver` бросает `DomainException`, если на миду 0 или больше 3 игроков, и матч приходит без `MatchDetail` с этим `DomainException` в `MatchFetchResult::getError()`. Зафиксировано тестами `ProSceneRoleResolverTest::test_resolveWithoutMiddlePlayerFails` и `MatchMapperPositionEstTest::test_noMiddleLayoutWithoutPositionEstFailsWithDomainException`.
- **2 игрока на миду и 3 на safe** (только без валидного `position_est`). Мидер-саппорт получает HARD_SUPPORT, и тройка на safe тоже раздаёт HARD_SUPPORT и SUPPORT. В итоге у команды две роли HARD_SUPPORT.
- В `ProSceneRoleResolver` есть неиспользуемые приватные методы `assertLaneComposition()` и `assertLaneCount()`.
- **`KDA::getCoefficient()`** считает `kills + assists / deaths` (неверные скобки), а при `deaths = 0` — деление на ноль.
- **Нераспарсенные матчи не маппятся.** У публичных нераспарсенных матчей (`version = null`) нет `lane`, `position_est`, большинства счётчиков, а у игроков со скрытым профилем `account_id = null` и `hero_id = 0`. Маппер падает с `SourceDataException` (`Hero ID must be at least 1, 0 given`), проверено 2026-10-30 на `publicMatches`. Про-матчи распарсены всегда.

## Инфраструктура

- `HttpStatusCode::TOO_MANY_REQUESTS` и `INTERNAL_SERVER_ERROR` не используются.
- `composer audit` показывает 2 advisory в зависимостях (vendor) — нужно посмотреть.

## У потребителя (`dota2-analytical-service`)

- **Бандл 2.0 ломает его импорты.** Совместимость при рефакторинге 2026-10-30 сознательно не сохранялась: классы переехали (`Domain\*` → `Match\*` / `Team\*` / `SharedKernel\*`, `Infrastructure\Exceptions\*` → `Exception\*`), порты переименованы (`MatchSourceInterface`, `TeamSourceInterface`), `matches()` возвращает `MatchService`, `getError()` — `?Dota2ApiExceptionInterface`. Потребитель подключает `dev-main` (теперь `2.0-dev`) и автовайрит `Dota2ApiClient` — при обновлении править `use` и, если он переопределяет сервисы бандла, их id. `packages/test` ломается так же (`ClientConfig` вместо прежнего конфига Builder).
- `FetchMatchFromApiHandler` не читает `getError()`, а при `null` бросает общее «Match detail is null». Реальная причина (например, ошибка ролей) теряется в логах.
- В `config/services.yaml` есть ссылки на `Domain\Services\RegionResolverInterface` и `Infrastructure\Services\RegionResolver`. В бандле таких классов нет.
- Конфиг `dota2_api` в старом формате. Работает, но выдаёт deprecation — нужно перейти на `opendota.*`.

## Исправлено

- **Рефакторинг по контекстам DDD (2026-10-30).** HTTP-ошибка одного матча в `getMatches()` больше не роняет генератор — она приходит `SourceRequestException` в `getError()`, поток идёт дальше (включая обрыв и таймаут чанка). `getMatches()` параллелится с первого запроса: `ApiGateway::request()` ленивый, статус проверяет `decode()`. `MatchExtractor` работает с декодированным массивом и не даёт warning на отсутствующих ключах (нет ключа → `null`, обязательность проверяет `MatchMapper`); битый JSON → `SourceDataException`. Все исключения реализуют `Dota2ApiExceptionInterface`. Подробности — architecture.md.
- **Проверка полей источника (2026-10-30).** `getPlayersByTeam()` падал на бывших игроках (`name` / `is_current_team_member` = `null`). `getTeams()` падал на командах с пустым `tag`. Удалена пустая фикстура `team_responses.json`.
- **Рефакторинг 2026-10-11** (по [refactoring-plan.md](refactoring-plan.md)). `dd()` в `getMatch()`; `TeamName(null)` / `TeamId(null)`; лесные линии и `lane_role`; ретраи 429/5xx; тип таймаута; подмена `http_client` приложения; двойной граф DI и двойная регистрация `RoleResolverInterface`; зависимость домена от инфраструктуры (`MatchResult`); `ApiGateway` на конкретном классе; `PlayerProviderInterface` и `ProviderException` удалены; `toCollection()`, возвращавший `[]`, удалён; схема `phpunit.xml.dist`; `strict_types` везде.
