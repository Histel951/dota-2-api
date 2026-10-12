# ТЗ: рефакторинг бандла dota-api

Дата: 2026-10-11 · Статус: **выполнено** в ветке `refactoring/stabilize-bundle` (один коммит на этап)

## Итог и отступления от ТЗ

| Пункт | Что сделано иначе | Почему |
|---|---|---|
| 1.4 | Добавлен только `lane_role`, `is_roaming` не используется. Лес (lane 4/5) → линия по `lane_role` с переводом в сторону Radiant | В OpenDota `lane = 5` — это лес Dire, а не роуминг, а `lane_role` задан относительно команды. Если просто пропустить поля, у Dire перевернулись бы safe и offlane |
| 1.5 / 3.2 | На этапе 1 ретраи — `RetryableHttpClient` внутри `OpenDotaHttpClient`. На этапе 2 класс заменён на `OpenDotaHttpClientFactory`, поэтому пункт 3.2 закрыт ещё в этапе 2 | меньше промежуточного кода |
| 2.x | Интерфейсы провайдеров остались автовайрируемыми сервисами | `dota2-analytical-service` автовайрит `Dota2ApiClient` из них |
| 3.3 | Дополнительно: все доменные `Invalid*Exception` теперь наследуют `DomainException`. Маппер также оборачивает `TypeError` (неожиданный тип поля) | иначе пришлось бы перечислять 13 классов в `catch` |
| 4.2 | Секция `stratz` в конфиг не добавлена, добавлен `max_retries` | YAGNI: ключ, который ничего не делает, путает. Секция добавится вместе с реализацией |
| 5.2 | Переопределение `toCollection()` удалено, работает базовый `AbstractMapper::toCollection()` | вместо ловушки с `[]` — рабочий метод |
| 5.3 | `test.php`, `promt.md` добавлены в `.gitignore`, файлы не удалены | это локальные файлы, их не стоит удалять без спроса |

Остаток технического долга — в [known-issues.md](known-issues.md).

---

## 1. Цель

Привести бандл в состояние, при котором:
1. ошибки в данных одного матча не роняют процесс, а ретраи действительно работают;
2. в Symfony используется один граф сервисов, и бандл не ломает `http_client` приложения;
3. слои Domain и Infrastructure не перемешаны, и STRATZ можно добавить, не трогая OpenDota-часть;
4. основная логика (мапперы, `ProSceneRoleResolver`) закрыта тестами.

Публичный API клиента (`Dota2ApiClient::matches()` / `teams()` и методы провайдеров) сохраняется. Ломающие изменения перечислены в разделе 4.

## 2. Вне рамок

- Реализация STRATZ-провайдеров (отдельное ТЗ). Здесь только подготовка точек расширения.
- Новые показатели, например подборы лотусов.
- Изменение алгоритма определения ролей. Его только покрываем тестами как есть.

## 3. Этапы

Этапы идут по порядку, каждый оформляется отдельным PR в `main`. Этап 0 обязателен до остальных: без него нельзя доказать, что рефакторинг ничего не сломал.

### Этап 0. Страховочные тесты · S

| # | Задача |
|---|---|
| 0.1 | Перенести `match.json` в `tests/Fixtures/opendota/match_8794744726.json` |
| 0.2 | Характеризационный тест `MatchOpenDotaMapper` (+`MatchExtractor`) на фикстуре: id, длительность, победитель, регион, у каждого из 10 игроков KDA, GPM, роль, smoke, tormentor, camps_stacked |
| 0.3 | Юнит-тесты `ProSceneRoleResolver`: стандартная раскладка 2-1-2 (Radiant и Dire), 2 и 3 игрока на миду, 3 на одной линии, неразрешимая раскладка → `DomainException`, ≠5 игроков → `DomainException` |
| 0.4 | `phpunit.xml.dist`: схема PHPUnit 11, убрать `XDEBUG_MODE=coverage` из обязательных env (сейчас без драйвера покрытия выдаётся warning) |

**Критерий приёмки:** `vendor/bin/phpunit` зелёный без warnings. Тесты 0.2–0.3 фиксируют текущее поведение, и падающие баги из этапа 1 помечены отдельно.

### Этап 1. Критические баги · S

| # | Проблема | Как проявляется | Решение |
|---|---|---|---|
| 1.1 | `dd($e->getMessage())` в `OpenDotaMatchProvider::getMatch()` | любая ошибка маппинга завершает процесс | убрать и вернуть `MatchResult($id, null, $e)`, как в `getMatches()` |
| 1.2 | `TeamName(null)` в конструкторе делает `return null` и оставляет readonly-свойство неинициализированным | `getValue()` → `Error: must not be accessed before initialization` (проверено) | всегда присваивать `$this->value = $value` |
| 1.3 | `TeamId(null)`: `getValue(): string` возвращает `null` | `TypeError` (проверено); плюс deprecation неявного nullable в PHP 8.4+ | сигнатура `?string getValue()`, параметр `string\|int\|null $value = null` |
| 1.4 | `MatchExtractor::PLAYER_FIELDS` не содержит `lane_role`, `is_roaming` | роумеры (`lane = 5`) всегда попадают в SAFE | добавить поля и покрыть тестом |
| 1.5 | Ретраи в `OpenDotaHttpClient` ловят `ClientException` в `request()`, но HttpClient ленивый, а 5xx — это `ServerException` | 429 и 5xx не повторяются | заменить самописный цикл на `Symfony\Component\HttpClient\RetryableHttpClient` (по умолчанию повторяет 423/425/429/5xx с экспоненциальной задержкой, учитывает `Retry-After`) |
| 1.6 | `ConfigurationHttpClient::getTimeout(): ?string` при свойстве `?int`, в файле нет `strict_types` | работает за счёт неявного приведения | `?int` + `declare(strict_types = 1)` |

**Критерий приёмки:** тесты на каждый пункт. Матч без команд (`radiant_name = null`) маппится без ошибок.

### Этап 2. Единый DI и изоляция HTTP-клиента · M

Проблемы:
- `services.php` регистрирует `Symfony\Contracts\HttpClient\HttpClientInterface` и тем самым **перекрывает `http_client` приложения-потребителя**.
- Граф собирается дважды: `Dota2ApiExtension` автовайрит провайдеры и мапперы, а `Dota2ApiClient` создаётся через `Dota2ApiClientBuilder`, который делает всё заново через `new`. Сервисы из контейнера не используются.
- `RoleResolverInterface` регистрируется дважды (в `services.php` и в Extension).

| # | Задача |
|---|---|
| 2.1 | Свой приватный сервис `dota2_api.opendota.http_client`: `HttpClient::create()` → `ScopedHttpClient` (base_uri, timeout, query `api_key`) → `RetryableHttpClient`. Глобальный `HttpClientInterface` бандл не трогает |
| 2.2 | Вся регистрация — в одном месте (`services.php`). Extension только обрабатывает конфиг и выбирает реализации по `source` |
| 2.3 | `Dota2ApiClient` автовайрится из `MatchesProviderInterface` и `TeamProviderInterface`. Фабричный вызов `Dota2ApiClientBuilder::build()` из DI убрать |
| 2.4 | `Dota2ApiClientBuilder` оставить только для использования без Symfony. Внутри он собирает граф через ту же `ProviderFactory` без дублирования логики |
| 2.5 | Тест DI: `ContainerBuilder` + `Dota2ApiExtension`, после компиляции получаются `Dota2ApiClient`, оба провайдера и app-шный `http_client` не подменён |

**Критерий приёмки:** в тестовом ядре с `framework.http_client` клиент бандла и клиент приложения — разные сервисы. Публичен только `Dota2ApiClient`.

### Этап 3. Границы слоёв · M

| # | Задача |
|---|---|
| 3.1 | `Infrastructure\Provider\Results\MatchResult` → `Domain\Providers\Results\MatchFetchResult`. Домен перестаёт импортировать Infrastructure, и пропадает путаница с VO `Domain\Entities\Match\ValueObjects\MatchResult` |
| 3.2 | `ApiGateway` зависит от `HttpClientInterface`, а не от конкретного `OpenDotaHttpClient`. После 2.1 `OpenDotaHttpClient` не нужен, удалить |
| 3.3 | Мапперы матча оборачивают доменные `Invalid*Exception` в `MappingException`, как это уже сделано в `TeamOpenDotaMapper`. Список `@throws` у `toEntity()` сокращается до одного |
| 3.4 | `strict_types` во всех файлах `src/` |

**Критерий приёмки:** `grep -r "Infrastructure" src/Domain` пуст, все тесты зелёные.

### Этап 4. Подготовка к STRATZ · M

Сам STRATZ здесь не реализуется (YAGNI). Делаем так, чтобы он добавлялся новыми файлами, без правок OpenDota-кода.

| # | Задача |
|---|---|
| 4.1 | Группировка по источнику: `Infrastructure/OpenDota/{Http,Mapper,Provider,Extractor}`; общее (`AbstractMapper`, исключения) остаётся в `Infrastructure/` |
| 4.2 | Конфиг по источникам (`base_url` и ключ у каждого свой; STRATZ — Bearer-токен, GraphQL): |

```yaml
dota2_api:
  source: opendota
  timeout: 30
  opendota:
    base_url: 'https://api.opendota.com/api/'
    api_key: '%env(default::OPENDOTA_API_KEY)%'
  stratz:
    base_url: 'https://api.stratz.com/graphql'
    token: '%env(default::STRATZ_TOKEN)%'
```

| # | Задача |
|---|---|
| 4.3 | Выбор реализаций по `source` — через `ProviderFactory` (уже есть) и в DI, и в Builder. `source: stratz` до реализации даёт понятную ошибку на этапе компиляции контейнера, а не в рантайме |

**Критерий приёмки:** чтобы добавить STRATZ, нужно создать `Infrastructure/Stratz/*` и одну ветку в выборе фабрики; файлы OpenDota при этом не меняются.

### Этап 5. Чистка · S

| # | Задача |
|---|---|
| 5.1 | Удалить `PlayerProviderInterface` (не реализован, не используется). Вернуть его вместе с реализацией, когда понадобится |
| 5.2 | `MatchOpenDotaMapper::toCollection()` (`@deprecated`, возвращает `[]`) — бросать `LogicException` либо убрать наследование списочного метода |
| 5.3 | `index.php` → `examples/fetch_match.php`; `test.php`, `promt.md` убрать из репозитория или добавить в `.gitignore` |
| 5.4 | Решить судьбу незакоммиченного `symfony/profiler-pack` в `require-dev`: бандлу он не нужен |
| 5.5 | Обновить `.claude/docs/*` и `known-issues.md` |

## 4. Совместимость

Ломающие изменения публичного API:
- новый namespace и имя результата матча (3.1): `Infrastructure\Provider\Results\MatchResult` → `Domain\Providers\Results\MatchFetchResult`;
- новая структура конфига (4.2); `base_url` и `api_key` в корне можно поддержать как deprecated-алиасы на один релиз;
- `TeamId::getValue()` теперь `?string`;
- удалён `PlayerProviderInterface`.

Фактически после выполнения:
- старые ключи конфига работают (deprecated) — **`dota2-analytical-service` продолжает работать без изменений**, проверено тестом с его конфигом и его способом автовайринга;
- перенесены namespace: мапперы → `Infrastructure\OpenDota\Mapper\*`, провайдеры и `OpenDotaProviderFactory` → `Infrastructure\OpenDota\Provider\*`, `MatchExtractor` → `Infrastructure\OpenDota\Extractor\*`;
- удалены `OpenDotaHttpClient`, `ProviderException`, `PlayerProviderInterface`;
- из DI больше не публичны мапперы, `ExtractorInterface`, `RoleResolverInterface` (у потребителей не используются);
- `ConfigurationHttpClient`, `Dota2ApiClientBuilder`, `ApiSource` — без изменений API (`packages/test` работает как раньше).

Если у бандла уже есть внешние потребители — выпускать как новую major-версию. Если пока только свои проекты — обновить их вместе с бандлом.

## 5. Риски

| Риск | Митигация |
|---|---|
| Рефакторинг меняет вывод ролей или статистики | Этап 0 — характеризационные тесты на реальном матче до любых правок |
| `RetryableHttpClient` с `sleep` удлиняет пакетный `getMatches()` | ограничить `max_retries` конфигом; ретраи асинхронные, поток не блокируют |
| Фикстура одного матча не покрывает редкие раскладки | для ролей — синтетические юнит-тесты (0.3), а не только фикстура |
