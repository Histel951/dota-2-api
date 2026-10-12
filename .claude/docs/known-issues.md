# Известные проблемы и технический долг

Актуально на 2026-10-11, после рефакторинга по [refactoring-plan.md](refactoring-plan.md). Перед тем как опираться на пункт, стоит перепроверить его по коду.

## Данные и домен

- **Анонимные игроки.** Если у игрока скрыт профиль (`account_id = null`, нет `personaname`), то `PlayerId` и `PlayerName` не создаются, и весь матч уходит в `MappingException`. Для про-матчей это редкость, но в пабах встречается.
- **Нет мида → ошибка ролей.** `ProSceneRoleResolver` бросает `DomainException`, если на миду 0 или больше 3 игроков. Матч при этом не маппится. Зафиксировано тестом `test_resolveWithoutMiddlePlayerFails`.
- **2 игрока на миду и 3 на safe.** Мидер-саппорт получает HARD_SUPPORT, и тройка на safe тоже раздаёт HARD_SUPPORT и SUPPORT. В итоге у команды две роли HARD_SUPPORT.
- В `ProSceneRoleResolver` есть неиспользуемые приватные методы `assertLaneComposition()` и `assertLaneCount()`.
- **`KDA::getCoefficient()`** считает `kills + assists / deaths` (неверные скобки), а при `deaths = 0` — деление на ноль.
- Нераспарсенные матчи (без `lane`, `item_uses` и т.д.) не отличаются от распарсенных. Отсутствующие счётчики становятся 0, а роли не определяются.

## Инфраструктура

- `ApiGateway::request()` бросает `ApiGatewayException` до блока `try`, поэтому HTTP-ошибки из `getMatch()` не попадают в `MatchFetchResult`. А в `getMatches()` такая ошибка прерывает весь генератор.
- **`getMatches()` фактически не параллелится на старте.** `ApiGateway::request()` сразу вызывает `getStatusCode()`, а он ждёт заголовков ответа. Поэтому каждый новый запрос ждёт заголовков предыдущего. Параллельной остаётся только загрузка тел ответов.
- `MatchExtractor` читает обязательные поля игрока через `$data[$field]`: если ключа нет, будет warning и `null`.
- `HttpStatusCode::TOO_MANY_REQUESTS` и `INTERNAL_SERVER_ERROR` больше не используются.
- Фикстура `tests/Fixtures/team_responses.json` нигде не используется.
- `composer audit` показывает 2 advisory в зависимостях (vendor) — нужно посмотреть.

## У потребителя (`dota2-analytical-service`)

- `FetchMatchFromApiHandler` не читает `getError()`, а при `null` бросает общее «Match detail is null». Реальная причина (например, ошибка ролей) теряется в логах.
- В `config/services.yaml` есть ссылки на `Domain\Services\RegionResolverInterface` и `Infrastructure\Services\RegionResolver`. В бандле таких классов нет.
- Конфиг `dota2_api` в старом формате. Работает, но выдаёт deprecation — нужно перейти на `opendota.*`.

## Исправлено рефакторингом

`dd()` в `getMatch()`; `TeamName(null)` / `TeamId(null)`; лесные линии и `lane_role`; ретраи 429/5xx; тип таймаута; подмена `http_client` приложения; двойной граф DI и двойная регистрация `RoleResolverInterface`; зависимость Domain → Infrastructure (`MatchResult`); `ApiGateway` на конкретном классе; `PlayerProviderInterface` и `ProviderException` удалены; `toCollection()`, возвращавший `[]`, удалён; схема `phpunit.xml.dist`; `strict_types` везде.
