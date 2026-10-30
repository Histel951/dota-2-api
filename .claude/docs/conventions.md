# Соглашения

## Стиль кода

- `declare(strict_types = 1);` в начале файла (в части старых файлов его нет; в новых — ставить).
- Constructor property promotion с `private readonly`. VO и неизменяемые сущности объявляются `final readonly class`.
- Именованные аргументы при создании сущностей и VO (`new KDA(kills: ..., deaths: ..., assists: ...)`).
- Мапперы корневых сущностей (`MatchMapper`, `Team*Mapper`) наследуют `Infrastructure/Mapping/AbstractMapper`; поля описываются константами `REQUIRED_FIELDS` / `OPTIONAL_FIELDS` (`private const array`). Обязательные проверяет `validate()`, необязательные достаёт `extractOptionalFields()`. Ошибки данных оборачиваются в `SourceDataException` только в `toEntity()` (`MatchMapper` ловит `DomainException|TypeError|ValueError`, `Team*Mapper` — `Throwable`); вспомогательные `PlayerMapper` / `DraftMapper` / `LaneNormalizer` — `final`, `AbstractMapper` не наследуют и ничего не оборачивают.
- Над методами — PHPDoc `@throws` со всеми доменными исключениями. Порты — `@throws SourceRequestException|SourceDataException`.
- Комментарии и сообщения в PHPDoc пишутся на русском, тексты исключений — на английском.
- Домен не знает про HTTP и формат источника и не говорит на его языке: фабрики VO называются по смыслу (`fromGames`, `fromRecord`, `fromScore`). Всё, что связано с форматом источника, живёт в `Infrastructure/<Source>/`; суффикс источника в имени класса не нужен — его задаёт папка.
- Все исключения бандла реализуют `Exception\Dota2ApiExceptionInterface`.
- Ветки: `feature/*`, `bugfix/*`, `refactoring/*` → PR в `main`. Коммиты в стиле `fix - ...`.

## Раскладка

- Папка по **понятию**, а не по типу: `Match/Domain/Player`, `Draft`, `Side`, `Team/Domain/History`, `Roster`, `Heroes` — а не `ValueObjects`, `Enums`.
- Исключения контекста — в его `Exception/` (`Match/Domain/Exception`, `Team/Domain/Exception`) и наследуют `SharedKernel\Exception\DomainException`.
- `SharedKernel` — только то, что реально используют оба контекста (проверяй grep'ом). Нужно одному — живёт в контексте (так `WinrateStats` и `HeroName` остались в Team).
- Порт — в `<Context>/Port`, оркестрация (роли, обёртка ошибок) — в `<Context>/Application`, адаптер источника — в `Infrastructure/<Source>/<Context>`.
- Правила зависимостей — в [architecture.md](architecture.md), проверяются `LayerDependenciesTest`.

## Добавить новый показатель игрока в матче

На примере `camps_stacked`:

1. VO в `Match/Domain/Player/Stats/` — `final readonly class CampsStacked extends Counter {}`.
2. Добавить поле и геттер в подходящую группу статистики (`CreepsStats`, `WardingStats`, `UtilityStats`, ... в `Match/Domain/Player/Stats/`).
3. Добавить ключ из ответа OpenDota в `Infrastructure/OpenDota/Match/MatchExtractor::PLAYER_FIELDS`. **Без этого шага поле до маппера не дойдёт.** Имя ключа берётся из фикстуры или из вывода `php tools/refresh-opendota-fixtures.php`, не по памяти: `MatchSourceFieldsTest` упадёт, если ключа из `PLAYER_FIELDS` нет в фикстуре. Вложенный ключ (`item_uses.<item>`) читается в маппере напрямую, его нужно добавить в `tests/Support/OpenDota/SourceFieldsChecker` (`MATCH_PLAYER_NESTED_ANY`) руками.
4. Заполнить поле в `Infrastructure/OpenDota/Match/PlayerMapper::map()`.
5. Если добавляется новая группа в `MatchPlayerPerformance`, не забыть `withRole()`: он пересоздаёт объект со всеми полями. Также обновить хелперы `createPlayer()` в `ProSceneRoleResolverTest` и `MatchDetailTest`.
6. Тесты: значение на фикстуре в `PlayerMapperTest` и ожидание в `MatchMapperTest::playersProvider()` (данные из `tests/Fixtures/opendota/`).

## Добавить новый эндпоинт

1. Метод в порте контекста (`<Context>/Port/*SourceInterface`) с `@throws SourceRequestException|SourceDataException`. Новый контекст — свои `Domain/` и `Port/`, без зависимостей на другой контекст.
2. Сущность и VO в `<Context>/Domain/...` (папка по понятию).
3. Маппер `Infrastructure/OpenDota/<Context>/*Mapper extends AbstractMapper`.
4. Реализация в `Infrastructure/OpenDota/<Context>/OpenDota*Source` через `ApiGatewayInterface::get()`.
5. Подключение маппера в `OpenDotaSourceFactory` (DI и Builder берут адаптеры оттуда, отдельно регистрировать не нужно). Новый порт — метод `create*Source()` в `SourceFactoryInterface`, сервис-фабрика в `services.php` и аргумент `Dota2ApiClient` / `Dota2ApiClientBuilder`.
6. Unit-тест источника по образцу `tests/Unit/Infrastructure/OpenDota/<Context>/`.
7. Реальная фикстура ответа: добавить эндпоинт в `tools/refresh-opendota-fixtures.php`, сохранить через `--write` (compact JSON, списки — первые 20). Метод `check*()` в `tests/Support/OpenDota/SourceFieldsChecker` и кейс в `tests/Unit/Infrastructure/OpenDota/SourceFields/`. Тест маппера на фикстуре по образцу `Team*MapperTest`.

## Тесты

- Структура `tests/Unit/` повторяет `src/` (`Match/Domain/Role`, `Match/Application`, `Infrastructure/OpenDota/{Match,Team}`, ...).
- `tests/Unit/Architecture/LayerDependenciesTest` проверяет правила зависимостей слоёв по всему `src/`; новый верхнеуровневый namespace — добавить в его `LAYERS`.
- HTTP мокается через `Symfony\Component\HttpClient\MockHttpClient`, без сети.
- Для 404/429/5xx/таймаутов gateway в тестах собирается как в проде: `new ApiGateway(OpenDotaHttpClientFactory::create(new MockHttpClient(...), $config))` — с `RetryableHttpClient`, иначе поведение ретраев и ошибок потока не проверяется (образец — `OpenDotaMatchSourceTest::createSource()`). `MatchService` тестируется с моком `MatchSourceInterface`.
- `phpunit.xml.dist` падает на warnings и deprecations — новые deprecations сразу чинить.

## Поля источника

Ожидаемые кодом ключи (`REQUIRED_FIELDS` / `OPTIONAL_FIELDS` `MatchMapper` и `Team*Mapper`, `MatchExtractor::*_FIELDS` и вложенные ключи) проверяются по реальным фикстурам `tests/Fixtures/opendota/*.json`. Это делают `tests/Support/OpenDota/SourceFieldsChecker` и тесты `tests/Unit/Infrastructure/OpenDota/SourceFields/`; константы читаются через reflection, дублировать их в тестах не нужно.

- REQUIRED — ключ есть и не `null` в каждом элементе; OPTIONAL — ключ есть хотя бы в одном элементе; поля игрока — ключ есть у каждого игрока.
- Если в реальном ответе обязательное поле бывает `null`, это сигнал, что поле на самом деле необязательное (так было с `is_current_team_member` и `name` игроков команды).
- `php tools/refresh-opendota-fixtures.php` — dry-run: скачивает живые ответы OpenDota, печатает дифф ключей с фикстурами (`+` новые, `-` пропавшие, `~` сменился тип) и проверяет ожидаемые поля. Exit 1 — пропало поле, которое читает код, или сетевая ошибка. `--write` перезаписывает фикстуры команд, `--include-match` — ещё и фикстуру матча (на ней держатся характеризационные тесты, перезаписывать осознанно).
