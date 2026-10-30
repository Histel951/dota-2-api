# dota-api — контекст проекта

Symfony-бандл (`histel951/dota-2-api`, тип `symfony-bundle`) для запросов к общедоступным API по Dota 2 — **OpenDota** и **STRATZ**.
Бандл скрывает HTTP, ретраи и формат ответов конкретного API. Наружу он отдаёт типизированные доменные сущности: матчи, команды, игроки команды, герои команды.

- Namespace: `Histel951\Dota2Api\` → `src/`, тесты: `Histel951\Dota2Api\Tests\` → `tests/`
- PHP `>=8.3`, Symfony `6.4 | 7.x` (http-client, http-kernel, config, dependency-injection)
- Тесты: PHPUnit 11 (`vendor/bin/phpunit`), фикстуры в `tests/Fixtures/`
- Версия: `dev-main` → `2.0-dev` (2.0 ломает совместимость с 1.x: структура и имена классов изменены)
- Архитектура: DDD по контекстам (Match, Team, SharedKernel; Domain / Application / Port), адаптеры источников в `Infrastructure/`, публичный вход — `Client/` и `Exception/`. Принципы SOLID, YAGNI, DRY, KISS

## Статус источников

| Источник | Статус |
|---|---|
| OpenDota (`https://api.opendota.com/api/`) | реализован: матчи и команды |
| STRATZ | только значение `ApiSource::STRATZ`. `source: stratz` в DI → `InvalidConfigurationException`, в `Dota2ApiClientBuilder` → `ClientConfigurationException` |

## Потребители

Оба написаны под 1.x и при переходе на 2.0 требуют правки импортов (см. known-issues.md).

- `dota2-analytical-service` (`../../dota2-analytical-service/symfony`). Подключает `dev-main` и переопределяет `Dota2ApiClient` с `autowire: true`, то есть берёт зависимости клиента из DI автовайрингом. Конфиг использует в старом формате (`base_url`/`api_key` в корне).
- `packages/test`. Собирает клиент через `Dota2ApiClientBuilder`.

## Документы

- [architecture.md](architecture.md) — слои и контексты, правила зависимостей, путь запроса, пакетная загрузка, исключения, DI, конфиг бандла, новый источник
- [api-coverage.md](api-coverage.md) — какие эндпоинты покрыты, что возвращают, особенности данных OpenDota
- [domain.md](domain.md) — доменная модель матча и команд, SharedKernel, как определяются роли игроков
- [conventions.md](conventions.md) — стиль, раскладка по контекстам, рецепты «новый показатель» и «новый эндпоинт», тесты, поля источника
- [known-issues.md](known-issues.md) — оставшийся технический долг и что исправлено
- [refactoring-plan.md](refactoring-plan.md) — **исторический**: ТЗ рефакторинга 2026-10-11 (выполнено) по старой структуре (`Domain/`, провайдеры); пути и имена классов в нём устарели

## Агенты и скиллы

- `/develop-feature` (`.claude/skills/develop-feature/`) — интервью → ТЗ → реализация вертикальными срезами → финальная проверка
- `.claude/agents/`: `architect` (проектирование, только чтение), `domain-implementer` (домен контекстов, мапперы, экстрактор), `api-designer` (порты, Application, адаптеры источников, DI, конфиг, клиент), `test-writer` (PHPUnit и фикстуры), `ddd-reviewer` (ревью границ слоёв, только чтение)

## Прочее в корне

- `examples/fetch_match.php` — сборка клиента без Symfony и вывод статистики матча
- `tools/refresh-opendota-fixtures.php` — дифф ключей живого OpenDota с фикстурами и их обновление (dev)
- `config/packages/dota2_api.yaml` — пример конфигурации для приложения-потребителя
- `promt.md`, `test.php` — локальные черновики, в `.gitignore`
