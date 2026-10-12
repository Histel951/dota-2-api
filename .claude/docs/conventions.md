# Соглашения

## Стиль кода

- `declare(strict_types = 1);` в начале файла (в части старых файлов его нет; в новых — ставить).
- Constructor property promotion с `private readonly`. VO и неизменяемые сущности объявляются `final readonly class`.
- Именованные аргументы при создании сущностей и VO (`new KDA(kills: ..., deaths: ..., assists: ...)`).
- В мапперах поля описываются константами `REQUIRED_FIELDS` / `OPTIONAL_FIELDS` (`private const array`). Обязательные проверяет `validate()`, необязательные достаёт `extractOptionalFields()`.
- Над методами — PHPDoc `@throws` со всеми доменными исключениями.
- Комментарии и сообщения в PHPDoc пишутся на русском, тексты исключений — на английском.
- Домен не знает про HTTP и формат OpenDota. Всё, что связано с форматом источника, живёт в `Infrastructure` (мапперы и экстракторы с суффиксом `OpenDota`).
- Ветки: `feature/*`, `bugfix/*` → PR в `main`. Коммиты в стиле `fix - ...`.

## Добавить новый показатель игрока в матче

На примере `camps_stacked`:

1. VO в `Domain/Entities/Match/ValueObjects/` — `final readonly class CampsStacked extends Counter {}`.
2. Добавить поле и геттер в подходящую группу статистики (`CreepsStats`, `WardingStats`, `UtilityStats`, ...).
3. Добавить ключ из ответа OpenDota в `Infrastructure/OpenDota/Extractor/MatchExtractor::PLAYER_FIELDS`. **Без этого шага поле до маппера не дойдёт.**
4. Заполнить поле в `Infrastructure/OpenDota/Mapper/MatchOpenDotaMapper::createPlayer()`.
5. Если добавляется новая группа в `MatchPlayerPerformance`, не забыть `withRole()`: он пересоздаёт объект со всеми полями. Также обновить хелпер `createPlayer()` в `ProSceneRoleResolverTest`.
6. Добавить ожидаемое значение в `MatchOpenDotaMapperTest::playersProvider()` (данные из фикстуры `tests/Fixtures/opendota/`).

## Добавить новый эндпоинт

1. Метод в доменном интерфейсе провайдера (`Domain/Providers/*ProviderInterface`).
2. Сущность и VO в `Domain/Entities/...`.
3. Маппер `Infrastructure/OpenDota/Mapper/*OpenDotaMapper extends AbstractMapper`.
4. Реализация в `Infrastructure/OpenDota/Provider/OpenDota*Provider` через `ApiGatewayInterface::get()`.
5. Подключение маппера в `OpenDotaProviderFactory` (DI и Builder берут провайдеры оттуда, отдельно регистрировать не нужно).
6. Unit-тест провайдера по образцу `tests/Unit/Infrastructure/OpenDota/Provider/`. Фикстуры кладутся в `tests/Fixtures/opendota/`.

## Тесты

- Структура `tests/Unit/` повторяет `src/`.
- HTTP мокается через `Symfony\Component\HttpClient\MockHttpClient`, без сети.
- `phpunit.xml.dist` падает на warnings и deprecations — новые deprecations сразу чинить.
