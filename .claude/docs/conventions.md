# Соглашения

## Стиль кода

- `declare(strict_types = 1);` в начале файла (в части старых файлов его нет; в новых — ставить).
- Constructor property promotion с `private readonly`. VO и неизменяемые сущности объявляются `final readonly class`.
- Именованные аргументы при создании сущностей и VO (`new KDA(kills: ..., deaths: ..., assists: ...)`).
- Мапперы корневых сущностей (`MatchMapper`, `Team*Mapper` обоих источников) наследуют `Infrastructure/Mapping/AbstractMapper`; поля описываются константами `REQUIRED_FIELDS` / `OPTIONAL_FIELDS` (`private const array`). Обязательные проверяет `validate()`, необязательные достаёт `extractOptionalFields()`. Ошибки данных оборачиваются в `SourceDataException` только в `toEntity()` (`DomainException|TypeError|ValueError`; у OpenDota `Team*Mapper` — `Throwable`). Вспомогательные мапперы (`PlayerMapper`, `DraftMapper`, `LaneNormalizer` / `LaneMapper`) — `final`, `AbstractMapper` не наследуют и ничего не оборачивают.
- Над методами — PHPDoc `@throws` со всеми доменными исключениями. Порты — `@throws SourceRequestException|SourceDataException`.
- Комментарии и сообщения в PHPDoc пишутся на русском, тексты исключений — на английском.
- Домен не знает про HTTP и формат источника и не говорит на его языке: фабрики VO называются по смыслу (`fromGames`, `fromRecord`, `fromScore`). Имена полей ответа (`account_id`, `steamAccountId`, `playbackData`, …) встречаются только в `<Source>/Infrastructure/`. Суффикс источника в имени класса не нужен — его задаёт namespace (`OpenDota\…`, `Stratz\…`); исключение — адаптеры `OpenDota*Source` / `Stratz*Source`, фабрики и клиенты источников.
- Все исключения бандла реализуют `Exception\Dota2ApiExceptionInterface`.
- Ветки: `feature/*`, `bugfix/*`, `refactoring/*` → PR в `main`. Коммиты в стиле `fix - ...`.

## Раскладка

- **Источник = bounded context.** `OpenDota/` и `Stratz/` — у каждого свои `Match/{Domain,Application,Port}`, `Team/{Domain,Port}`, `Infrastructure/` и клиент (`OpenDotaClient`, `StratzClient`). Источники друг на друга не ссылаются; внутри источника Match и Team друг от друга не зависят.
- **`SharedKernel` — только одинаковое по смыслу у источников** (ids, имена, длительность, время старта, регион Valve, сторона, KDA/GPM/XPM, `Counter`, `WinrateStats`, драфт). Похожее, но разное по смыслу (линия: абсолютная у OpenDota, относительная у STRATZ; роль vs позиция; статистика игрока; команда) — у каждого источника своё. Перед переносом в `SharedKernel` проверь, что значение действительно совпадает у обоих источников (на одном и том же матче).
- Общий `Infrastructure/` (`Http`, `Mapping`) не знает про источники; транспорт источника (`ApiGateway`, `GraphQlGateway`) — в `<Source>/Infrastructure/`.
- Папка по **понятию**, а не по типу: `<Source>/Match/Domain/Player`, `Player/Stats`, `Side`, `<Source>/Team/Domain/History`, `Roster`, `Heroes` — а не `ValueObjects`, `Enums`.
- Исключения контекста — в его `Exception/` (`<Source>/Match/Domain/Exception`, `<Source>/Team/Domain/Exception`) и наследуют `SharedKernel\Exception\DomainException`.
- Порт — в `<Source>/<Context>/Port`, оркестрация (роли, обёртка ошибок) — в `<Source>/<Context>/Application`, адаптер — в `<Source>/Infrastructure/<Context>`.
- Правила зависимостей — в [architecture.md](architecture.md), проверяются `LayerDependenciesTest`.

## Добавить новый показатель игрока в матче

### OpenDota

На примере `camps_stacked`:

1. VO в `OpenDota/Match/Domain/Player/Stats/` — `final readonly class CampsStacked extends Counter {}` (`SharedKernel\Counter`).
2. Добавить поле и геттер в подходящую группу статистики (`CreepsStats`, `WardingStats`, `UtilityStats`, ... в `OpenDota/Match/Domain/Player/Stats/`).
3. Добавить ключ из ответа OpenDota в `OpenDota/Infrastructure/Match/MatchExtractor::PLAYER_FIELDS`. **Без этого шага поле до маппера не дойдёт.** Имя ключа берётся из фикстуры или из вывода `php tools/refresh-opendota-fixtures.php`, не по памяти: `MatchSourceFieldsTest` упадёт, если ключа из `PLAYER_FIELDS` нет в фикстуре. Вложенный ключ (`item_uses.<item>`) читается в маппере напрямую, его нужно добавить в `tests/Support/OpenDota/SourceFieldsChecker` (`MATCH_PLAYER_NESTED_ANY`) руками.
4. Заполнить поле в `OpenDota/Infrastructure/Match/PlayerMapper::map()`.
5. Если добавляется новая группа в `MatchPlayerPerformance`, не забыть `withRole()`: он пересоздаёт объект со всеми полями. Также обновить хелперы `createPlayer()` в `ProSceneRoleResolverTest` и `MatchDetailTest`.
6. Тесты: значение на фикстуре в `tests/Unit/OpenDota/Infrastructure/Match/PlayerMapperTest` и ожидание в `MatchMapperTest::playersProvider()` (данные из `tests/Fixtures/opendota/`).

### STRATZ

1. Проверить поле живьём: есть ли оно в схеме `MatchType` / `MatchPlayerType`, у распарсенного и нераспарсенного матча, абсолютное или относительное, в каких единицах. Сверить с OpenDota на том же матче (`9026055036`) — значение и методика могут отличаться (руны, стан).
2. VO в `Stratz/Match/Domain/Player/Stats/` — счётчик `final readonly class X extends Counter {}` или свой VO с валидацией (как `StunDuration`).
3. Поле в группе: всегда доступное — `Economy` / `Damage` / `Farm` (или новая группа в `MatchPlayer`); только у распарсенного — `ParsedStats`. Если STRATZ показатель не отдаёт — поля нет (не ноль).
4. Поле в `Stratz/Infrastructure/Match/MatchQuery::FIELDS` (фрагмент `MatchFields`). **Без этого STRATZ поле не вернёт.**
5. Заполнить в `Stratz/Infrastructure/Match/PlayerMapper` (`map()` или `parsedStats()`), все вложенные чтения — через `??`; формулу — константами (`SMOKE_ITEM_ID`, `STUN_UNITS_PER_SECOND`, …).
6. Перезаписать фикстуры матчей: `php tools/refresh-stratz-fixtures.php --write --include-match` (иначе в фикстуре поля нет).
7. Тесты: `tests/Unit/Stratz/Infrastructure/Match/PlayerMapperTest` (значение на фикстуре через `StratzFixtures` + синтетика для краёв), `MatchQueryTest` (поле в запросе); формула — в api-coverage.md → «Особенности данных STRATZ».

## Добавить новый эндпоинт

### OpenDota

1. Метод в порте (`OpenDota/<Context>/Port/*SourceInterface`) с `@throws SourceRequestException|SourceDataException`. Новый контекст — свои `Domain/` и `Port/`, без зависимостей на другой контекст.
2. Сущность и VO в `OpenDota/<Context>/Domain/...` (папка по понятию).
3. Маппер `OpenDota/Infrastructure/<Context>/*Mapper extends AbstractMapper`.
4. Реализация в `OpenDota/Infrastructure/<Context>/OpenDota*Source` через `ApiGatewayInterface::get()`.
5. Подключение маппера в `OpenDotaSourceFactory` (DI и Builder берут адаптеры оттуда). Новый порт — метод `create*Source()` в фабрике, сервис-фабрика в `Resources/config/opendota.php`, аргумент `OpenDotaClient` и сборка в `Dota2ApiClientBuilder::buildOpenDota()`.
6. Unit-тест источника по образцу `tests/Unit/OpenDota/Infrastructure/<Context>/`.
7. Реальная фикстура ответа: добавить эндпоинт в `tools/refresh-opendota-fixtures.php`, сохранить через `--write` (compact JSON, списки — первые 20). Метод `check*()` в `tests/Support/OpenDota/SourceFieldsChecker` и кейс в `tests/Unit/OpenDota/Infrastructure/SourceFields/`. Тест маппера на фикстуре по образцу `Team*MapperTest`.

### STRATZ

1. Проверить запрос живьём (токен, `User-Agent: STRATZ_API`, лимит 150/мин): поля, `null` у нераспарсенных/старых данных, окно groupBy.
2. Метод в порте `Stratz/<Context>/Port/*SourceInterface`; сущность и VO в `Stratz/<Context>/Domain/...` — то, что STRATZ реально отдаёт (nullable, где источник даёт `null`).
3. Запрос — константа в `Stratz/Infrastructure/<Context>/*Query` (переменные, а не подстановка строк).
4. Маппер `Stratz/Infrastructure/<Context>/*Mapper extends AbstractMapper`; контекст, которого нет в строке ответа (например `teamId`), источник подмешивает в строку перед маппингом.
5. Реализация в `Stratz/Infrastructure/<Context>/Stratz*Source` через `GraphQlGateway::query()` → `GraphQlResult::field()` (`null` → «Object not found»).
6. Подключение в `StratzSourceFactory`; новый порт — `create*Source()`, сервис-фабрика в `Resources/config/stratz.php`, аргумент `StratzClient` и `Dota2ApiClientBuilder::buildStratz()`.
7. Фикстура — цель в `tools/refresh-stratz-fixtures.php` (`file`, `query`, `variables`, `root`), сохранить `--write`. Тесты источника и маппера в `tests/Unit/Stratz/Infrastructure/<Context>/`.

## Тесты

- Структура `tests/Unit/` повторяет `src/` (`OpenDota/Match/Domain/Role`, `OpenDota/Infrastructure/{Match,Team}`, `Stratz/Match/Application`, `Stratz/Infrastructure/{Match,Team}`, ...).
- `tests/Unit/Architecture/LayerDependenciesTest` проверяет правила зависимостей слоёв по всему `src/`; новый верхнеуровневый namespace — в `COMMON_LAYERS`, новый источник — в `SOURCES`.
- HTTP мокается через `Symfony\Component\HttpClient\MockHttpClient`, без сети.
- Для 404/429/5xx/таймаутов gateway в тестах собирается как в проде: OpenDota — `new ApiGateway(OpenDotaHttpClientFactory::create(new MockHttpClient(...), $config))` (образец — `OpenDotaMatchSourceTest::createSource()`), STRATZ — `new GraphQlGateway(StratzHttpClientFactory::create(...))` (образец — `StratzMatchSourceTest`), иначе ретраи и ошибки потока не проверяются. `maxRetries` в таких тестах — 1–2 (задержки настоящие). `MatchService` тестируется с моком своего `MatchSourceInterface`.
- Одноимённые классы источников в одном тесте — через `use … as`.
- `phpunit.xml.dist` падает на warnings и deprecations — новые deprecations сразу чинить.

## Поля источника

**OpenDota.** Ожидаемые кодом ключи (`REQUIRED_FIELDS` / `OPTIONAL_FIELDS` `MatchMapper` и `Team*Mapper`, `MatchExtractor::*_FIELDS` и вложенные ключи) проверяются по реальным фикстурам `tests/Fixtures/opendota/*.json`. Это делают `tests/Support/OpenDota/SourceFieldsChecker` и тесты `tests/Unit/OpenDota/Infrastructure/SourceFields/`; константы читаются через reflection, дублировать их в тестах не нужно.

- REQUIRED — ключ есть и не `null` в каждом элементе; OPTIONAL — ключ есть хотя бы в одном элементе; поля игрока — ключ есть у каждого игрока.
- Если в реальном ответе обязательное поле бывает `null`, это сигнал, что поле на самом деле необязательное (так было с `is_current_team_member` и `name` игроков команды).
- `php tools/refresh-opendota-fixtures.php` — dry-run: скачивает живые ответы OpenDota, печатает дифф ключей с фикстурами (`+` новые, `-` пропавшие, `~` сменился тип; `tests/Support/KeyDiff`) и проверяет ожидаемые поля. Exit 1 — пропало поле, которое читает код, или сетевая ошибка. `--write` перезаписывает фикстуры команд, `--include-match` — ещё и фикстуру матча (на ней держатся характеризационные тесты, перезаписывать осознанно).

**STRATZ.** `SourceFieldsChecker` не нужен: GraphQL сам отвергает запрос с полем, которого нет в схеме (400 + `Cannot query field …` → `SourceRequestException`), а фикстуры — ответы ровно тех запросов, что делает код.

- `php tools/refresh-stratz-fixtures.php` — dry-run: выполняет `MatchQuery::build()` для `9026055036` и `8794744726` и все `TeamQuery::*` для команды `8261500`, печатает `KeyDiff` корневого поля (`data.m0`, `data.team`, `data.team.matches|players|heroes`) с фикстурой. Exit 1 — нет токена, сеть, не-200, `errors` в ответе или корневое поле `null` (фикстуры не трогаются).
- `--write` перезаписывает фикстуры команд (compact, матчи команды — первые 20), `--include-match` — ещё и фикстуры матчей (pretty-print).
- Токен — env `STRATZ_TOKEN` или ключ `STRATZ_TOKEN` в `.env.local`; в вывод не попадает. Скрипт делает 6 запросов подряд (лимит 150/мин).
