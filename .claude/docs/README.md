# dota-api — контекст проекта

Symfony-бандл (`histel951/dota-2-api`, тип `symfony-bundle`) для запросов к общедоступным API по Dota 2 — **OpenDota** и **STRATZ**.
Бандл скрывает HTTP, ретраи и формат ответов конкретного API. Наружу он отдаёт типизированные доменные сущности: матчи, команды, игроки команды, герои команды.

- Namespace: `Histel951\Dota2Api\` → `src/`, тесты: `Histel951\Dota2Api\Tests\` → `tests/`
- PHP `>=8.3`, Symfony `6.4 | 7.x` (http-client, http-kernel, config, dependency-injection)
- Тесты: PHPUnit 11 (`vendor/bin/phpunit`), фикстуры в `tests/Fixtures/{opendota,stratz}/`
- Версия: `dev-main` → `2.0-dev` (2.0 ломает совместимость с 1.x: структура, имена классов и конфиг изменены)
- Архитектура: DDD, **источник = bounded context** (`OpenDota/`, `Stratz/`, у каждого свои Match и Team: Domain / Application / Port / Infrastructure), общий словарь игры — `SharedKernel/`, общая инфраструктура без источника — `Infrastructure/`, публичный вход — `Client/` и `Exception/`. Принципы SOLID, YAGNI, DRY, KISS

## Статус источников

Оба доступны одновременно: `$client->openDota()` и `$client->stratz()`.

| Источник | Статус |
|---|---|
| OpenDota (REST, `https://api.opendota.com/api/`, ключ необязателен) | реализован: матчи (`getMatch`, `getMatches`), команды (`getTeams`, `getTeam`, матчи / игроки / герои команды) |
| STRATZ (GraphQL, `https://api.stratz.com/graphql`, нужен токен `STRATZ_TOKEN`) | реализован: матчи (`getMatch`, `getMatches`), команды (`getTeam`, матчи / игроки / герои команды; списка всех команд у STRATZ нет) |

## Потребители

Оба написаны под 1.x и при переходе на 2.0 требуют правки импортов, вызовов клиента и конфига (см. known-issues.md).

- `dota2-analytical-service` (`../../dota2-analytical-service/symfony`). Подключает `dev-main` и переопределяет `Dota2ApiClient` с `autowire: true`, то есть берёт зависимости клиента из DI автовайрингом. Конфиг в старом формате (`base_url`/`api_key` в корне) — с 2.0 контейнер с ним не собирается.
- `packages/test`. Собирает клиент через `Dota2ApiClientBuilder`.

## Документы

- [architecture.md](architecture.md) — слои и контексты, правила зависимостей, путь запроса OpenDota и STRATZ, пакетная загрузка, исключения, DI, Builder, конфиг, как добавить третий источник
- [api-coverage.md](api-coverage.md) — методы `openDota()` и `stratz()`, что возвращают, особенности данных OpenDota и STRATZ, фикстуры
- [domain.md](domain.md) — SharedKernel, модели OpenDota и STRATZ (матч, команды), роли OpenDota
- [conventions.md](conventions.md) — стиль, раскладка, рецепты «новый показатель» и «новый эндпоинт» для каждого источника, тесты, поля источника
- [known-issues.md](known-issues.md) — оставшийся технический долг и что исправлено
- [refactoring-plan.md](refactoring-plan.md) — **исторический**: ТЗ рефакторинга 2026-10-11 (выполнено) по старой структуре (`Domain/`, провайдеры); пути и имена классов в нём устарели

## Агенты и скиллы

- `/develop-feature` (`.claude/skills/develop-feature/`) — интервью → ТЗ → реализация вертикальными срезами → финальная проверка
- `.claude/agents/`: `architect` (проектирование, только чтение), `domain-implementer` (домен источников и SharedKernel, мапперы, экстрактор), `api-designer` (порты, Application, адаптеры источников, транспорт, DI, конфиг, клиент), `test-writer` (PHPUnit и фикстуры), `ddd-reviewer` (ревью границ слоёв, только чтение)

## Прочее в корне

- `examples/fetch_match.php` — сборка клиента без Symfony и вывод статистики матча OpenDota
- `examples/fetch_stratz_match.php` — то же для STRATZ (токен из env `STRATZ_TOKEN`)
- `tools/refresh-opendota-fixtures.php`, `tools/refresh-stratz-fixtures.php` — дифф ключей живого API с фикстурами и их обновление (dev)
- `config/packages/dota2_api.yaml` — пример конфигурации для приложения-потребителя
- `.env.local` — локальные секреты (`STRATZ_TOKEN`), в `.gitignore`; токен не печатать и не коммитить
- `promt.md`, `test.php` — локальные черновики, в `.gitignore`
