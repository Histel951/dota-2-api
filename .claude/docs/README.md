# dota-api — контекст проекта

Symfony-бандл (`histel951/dota-2-api`, тип `symfony-bundle`) для запросов к общедоступным API по Dota 2 — **OpenDota** и **STRATZ**.
Бандл скрывает HTTP, ретраи и формат ответов конкретного API. Наружу он отдаёт типизированные доменные сущности: матчи, команды, игроки команды, герои команды.

- Namespace: `Histel951\Dota2Api\` → `src/`, тесты: `Histel951\Dota2Api\Tests\` → `tests/`
- PHP `>=8.3`, Symfony `6.4 | 7.x` (http-client, http-kernel, config, dependency-injection)
- Тесты: PHPUnit 11 (`vendor/bin/phpunit`), фикстуры в `tests/Fixtures/`
- Архитектура: DDD (Domain / Infrastructure / Client), принципы SOLID, YAGNI, DRY, KISS

## Статус источников

| Источник | Статус |
|---|---|
| OpenDota (`https://api.opendota.com/api/`) | реализован: матчи и команды |
| STRATZ | только значение `ApiSource::STRATZ`. `source: stratz` в DI → `InvalidConfigurationException`, в `Dota2ApiClientBuilder` → `Dota2ApiClientBuilderException` |

## Потребители

- `dota2-analytical-service` (`../../dota2-analytical-service/symfony`). Подключает `dev-main` и переопределяет `Dota2ApiClient` с `autowire: true`, то есть **берёт провайдеры из DI по интерфейсам `MatchesProviderInterface` / `TeamProviderInterface`**. Конфиг использует в старом формате (`base_url`/`api_key` в корне).
- `packages/test`. Собирает клиент через `Dota2ApiClientBuilder` + `ConfigurationHttpClient`.

## Документы

- [architecture.md](architecture.md) — слои, путь запроса, DI и конфигурация бандла
- [api-coverage.md](api-coverage.md) — какие эндпоинты покрыты и что возвращают
- [domain.md](domain.md) — доменная модель матча и как определяются роли игроков
- [conventions.md](conventions.md) — соглашения по коду и рецепт добавления новой статистики или эндпоинта
- [known-issues.md](known-issues.md) — оставшийся технический долг
- [refactoring-plan.md](refactoring-plan.md) — ТЗ на рефакторинг (выполнено) и отступления от него

## Агенты и скиллы

- `/develop-feature` (`.claude/skills/develop-feature/`) — интервью → ТЗ → реализация вертикальными срезами → финальная проверка
- `.claude/agents/`: `architect` (проектирование, только чтение), `domain-implementer` (домен, мапперы, экстракторы), `api-designer` (порты, провайдеры, DI, конфиг, клиент), `test-writer` (PHPUnit и фикстуры), `ddd-reviewer` (ревью границ слоёв, только чтение)

## Прочее в корне

- `examples/fetch_match.php` — сборка клиента без Symfony и вывод статистики матча
- `tools/refresh-opendota-fixtures.php` — дифф ключей живого OpenDota с фикстурами и их обновление (dev)
- `config/packages/dota2_api.yaml` — пример конфигурации для приложения-потребителя
- `promt.md`, `test.php` — локальные черновики, в `.gitignore`
