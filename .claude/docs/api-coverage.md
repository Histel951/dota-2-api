# Покрытие API

Оба источника доступны одновременно: `$client->openDota()` и `$client->stratz()`. Модели у них разные (domain.md), семантика ошибок общая (architecture.md → «Исключения»).

## Область бандла

Бандл — для **про-сцены**: про-матчи, про-команды и их игроки. Публичные матчи (пабы) и анонимные игроки сознательно вне области и не поддерживаются:

- **Анонимные игроки.** Если у игрока скрыт профиль (OpenDota `account_id = null`, нет `personaname`; STRATZ `steamAccountId = null`), `PlayerId` и `PlayerName` не создаются, и весь матч уходит в `SourceDataException`. В про-матчах профили открыты; в пабах такое встречается.
- **Нераспарсенные паблик-матчи OpenDota не маппятся.** У них (`version = null`) нет `lane`, `position_est`, большинства счётчиков, а у игроков со скрытым профилем `account_id = null` и `hero_id = 0` → `SourceDataException` (`Hero ID must be at least 1, 0 given`), проверено 2026-10-30 на `publicMatches`. Про-матчи OpenDota распарсены всегда.
- Роли OpenDota берутся только из `position_est` (он есть у любого распарсенного матча); эвристики «по линиям» для матчей без него нет — у про-сцены она не нужна.

## OpenDota — `openDota()`

REST, `https://api.opendota.com/api/`, ключ необязателен (query `api_key`).

`matches()` возвращает `OpenDota\Match\Application\MatchService` — обёртка ошибок поверх `OpenDota\Match\Port\MatchSourceInterface` (`OpenDota/Infrastructure/Match/OpenDotaMatchSource`). `teams()` возвращает `OpenDota\Team\Port\TeamSourceInterface` (`OpenDota/Infrastructure/Team/OpenDotaTeamSource`) напрямую.

| Метод клиента | Endpoint | Результат | Маппер |
|---|---|---|---|
| `matches()->getMatch(MatchId)` | `GET matches/{id}` | `MatchFetchResult` (→ `MatchDetail`); HTTP-ошибка бросается | `MatchExtractor` + `MatchMapper` (`PlayerMapper`, `DraftMapper`, `LaneNormalizer`) |
| `matches()->getMatches(list<MatchId>, concurrency = 5)` | `GET matches/{id}` × N, параллельно | `Generator<MatchFetchResult>`; любая ошибка матча — в `getError()` | то же |
| `teams()->getTeams()` | `GET teams` | `list<Team>` | `TeamMapper` |
| `teams()->getTeam(TeamId)` | `GET teams/{id}` | `Team` | `TeamMapper` |
| `teams()->getMatchesByTeam(TeamId)` | `GET teams/{id}/matches` | `list<TeamMatch>` | `TeamMatchMapper` |
| `teams()->getPlayersByTeam(TeamId)` | `GET teams/{id}/players` | `list<TeamPlayer>` | `TeamPlayerMapper` |
| `teams()->getHeroesByTeam(TeamId)` | `GET teams/{id}/heroes` | `list<TeamHero>` | `TeamHeroMapper` |

Мапперы — в `OpenDota/Infrastructure/{Match,Team}/`. Игроки (`/players/*`) пока не покрыты.

## STRATZ — `stratz()`

GraphQL, `POST https://api.stratz.com/graphql`, обязателен токен (Bearer).

`matches()` возвращает `Stratz\Match\Application\MatchService` — обёртка ошибок поверх `Stratz\Match\Port\MatchSourceInterface` (`Stratz/Infrastructure/Match/StratzMatchSource`), без резолвера ролей. `teams()` возвращает `Stratz\Team\Port\TeamSourceInterface` (`Stratz/Infrastructure/Team/StratzTeamSource`) напрямую.

| Метод клиента | GraphQL | Результат | Маппер |
|---|---|---|---|
| `matches()->getMatch(MatchId, withPlaybackData = false)` | `MatchQuery::build([id], withPlaybackData)`: `m0: match(id) { ...MatchFields }` | `MatchFetchResult` (→ `MatchDetail`); HTTP/GraphQL-ошибка бросается | `MatchMapper` (`PlayerMapper`, `LaneMapper`, `DraftMapper`) |
| `matches()->getMatches(list<MatchId>, concurrency = 5, withPlaybackData = false)` | тот же запрос; матчей на запрос — `MatchQuery::batchSize()`: с `playbackData` `BATCH_SIZE_WITH_PLAYBACK` (= 1), без — `BATCH_SIZE` (= 5); до `concurrency` запросов параллельно | `Generator<MatchFetchResult>`; любая ошибка матча — в `getError()` | то же |
| `teams()->getTeam(TeamId)` | `TeamQuery::TEAM`: `team(teamId) { id name tag winCount lossCount lastMatchDateTime }` | `Team` | `TeamMapper` |
| `teams()->getMatchesByTeam(TeamId)` | `TeamQuery::MATCHES`: `team.matches(take: 100, skip: 0)` | `list<TeamMatch>` (последние 100) | `TeamMatchMapper` |
| `teams()->getPlayersByTeam(TeamId)` | `TeamQuery::PLAYERS`: `team.matchesGroupBy(groupBy: STEAM_ACCOUNT_ID, take: 20000)` | `list<TeamPlayer>` (вся история) | `TeamPlayerMapper` |
| `teams()->getHeroesByTeam(TeamId)` | `TeamQuery::HEROES`: `team.matchesGroupBy(groupBy: HERO, take: 20000)` + `constants.heroes { id displayName }` | `list<TeamHero>` (вся история) | `TeamHeroMapper` |

Запросы — константы в `Stratz/Infrastructure/{Match/MatchQuery, Team/TeamQuery}`; `MatchQuery::FIELDS` (фрагмент `MatchFields on MatchType`) — единственное место, где перечислены поля матча. Аналога `getTeams()` нет: у STRATZ нет списка всех команд.

**Режим `playbackData` (байбеки).** По умолчанию `playbackData` не запрашивается: фрагмент `MatchFields` строится без `MatchQuery::PLAYBACK_FIELDS`, матчи идут по 5 в запросе (алиасы `m0`…`m4`), `ParsedStats::getBuybacks()` = `null`, остальные поля заполнены как обычно. Байбеки нужны — включить явно:

```php
$results = $client->stratz()->matches()->getMatches($ids, $concurrency, withPlaybackData: true);
```

Тогда матчи идут по одному на запрос (STRATZ пересобирает `playbackData` не более чем для одного незакэшированного матча за запрос) — заметно медленнее: живой прогон 2026-10-30, 9 матчей — ≈1.5 с без `playbackData` против ≈11 с с ним. Так же работает `getMatch($id, withPlaybackData: true)`.

**Лимитер.** `dota2_api.stratz.rate_limit` (запросов в минуту, по умолчанию `null` — выключен; в Builder — `ClientConfig(rateLimitPerMinute: N)`). Запросы сверх лимита ждут окна, а не падают; ретраи тоже расходуют лимит. Состояние — в памяти процесса (known-issues.md).

## Особенности данных OpenDota

- `lane` — **абсолютная** позиция на карте: 1 bot, 2 mid, 3 top, 4 лес Radiant, 5 лес Dire. Значения 1–3 совпадают с `Lane::SAFE/MIDDLE/OFFLANE` со стороны Radiant.
- `lane_role` — линия **относительно команды игрока**: 1 safe, 2 mid, 3 offlane, 4 лес. Используется `LaneNormalizer::normalize()` только для леса (lane 4/5), с переводом в сторону Radiant.
- `is_roaming` — отдельный признак, с `lane = 5` не связан. В коде не используется.
- `Lane` в домене OpenDota — абсолютная (со стороны Radiant): у Dire `Lane::SAFE` (bot) — его оффлейн, `Lane::OFFLANE` (top) — его safe. В относительную линию бандл её не переводит; роль игрока — из `position_est`.
- Убийства Tormentor берутся `PlayerMapper` из `objectives` (`type = CHAT_MESSAGE_MINIBOSS_KILL`, совпадение по `player_slot`).
- Smoke — `item_uses.smoke_of_deceit` (количество активаций). Watchers — `ability_uses.ability_lamp_use`.
- Лотусы: использования — `item_uses.famango` / `great_famango` / `greater_famango`. Подборы OpenDota не отдаёт.
- Про-ник игрока — `name`, при его отсутствии — `personaname`.
- Регион — `region` → `Region::fromId()` (enum `RegionType`).
- Детальная статистика (`item_uses`, `lane`, `objectives`, ...) есть только у матчей, распарсенных по реплею (`version` не null).
- `players[].position_est` — оценка позиции 1–5 от самого OpenDota, только у распарсенных матчей. Единственный источник роли игрока: нет или не 1–5 → `PlayerRole::UNKNOWN` (domain.md → «Роли»).
- `/teams`: у ~12% команд `tag = ""` (иногда и `name = ""`). `/teams/{id}/players`: у бывших игроков `name` и `is_current_team_member` — `null`.
- Большие числа (`dire_logo` и т.п. > `PHP_INT_MAX`) `json_decode` читает как float, поэтому дифф скрипта показывает `~ dire_logo: float -> string`. Код это поле не читает.
- Реальные ответы: `tests/Fixtures/opendota/` — `match_8794744726.json` (полный распарсенный матч), `teams.json`, `team_8261500*.json` (Xtreme Gaming). Обновляются через `php tools/refresh-opendota-fixtures.php` (см. conventions.md → «Поля источника»).

## Особенности данных STRATZ

Разведка живого API — 2026-10-30.

**Доступ и лимиты**

- Нужен токен: `Authorization: Bearer <token>` (конфиг `dota2_api.stratz.token`, env `STRATZ_TOKEN`). Без токена — 403 JSON `"A bearer token is required"` → `SourceRequestException`.
- Нужен заголовок `User-Agent: STRATZ_API` — без него Cloudflare отвечает 403 HTML. `StratzHttpClientFactory` ставит его всегда.
- Лимиты: 8 запросов/с, 150/мин, 1500/ч, 15000/сут (заголовки `x-ratelimit-*`). Без `rate_limit` превышение даёт 429 → ретраи `RetryableHttpClient` → `SourceRequestException`; с `rate_limit` (например, 140) запросы ждут окна в пределах процесса.
- `matches(ids)` (пачка матчей одним полем) — только для админов (`"User is not an admin."`). Поэтому несколько матчей в одном запросе возможны только через алиасы `m0: match(id) … m1: match(id) …`; несуществующий матч → `null` у алиаса → «Object not found».
- `playbackData` STRATZ пересобирает из реплея не более чем для одного незакэшированного матча за запрос — отсюда `MatchQuery::BATCH_SIZE_WITH_PLAYBACK = 1`. **Режим с байбеками (по умолчанию) медленный:** один матч — один запрос, каждый незакэшированный ждёт пересборку реплея, ускоряет только `$concurrency`. Без байбеков — `withPlaybackData: false` (5 матчей на запрос). Если `playbackData` не пришёл (или не запрошен), `ParsedStats::getBuybacks()` = `null`.
- Ошибки запроса (неизвестное поле, неверный тип переменной) приходят со статусом 400 и `errors` — дрейф схемы ловится сразу (`Cannot query field …`).

**Матчи**

- **Нераспарсенные матчи** — не ошибка: потребителю нужно проверять `MatchDetail::isParsed()`. Свежие (≲ 4 недели) STRATZ ещё не распарсил, бывают и старые (фикстура `8794744726`). У них `parsedDateTime = null`, `lane`/`position` = null, `pickBans` = null, `stats` частичные → в домене `isParsed = false`, `Lane::UNKNOWN`, `position = null`, `draft = null`, `parsedStats = null`. Плоские поля игрока (KDA, GPM/XPM, net worth, gold spent, урон, last hits/denies) есть всегда.
- Название команды (`radiantTeam.name` / `direTeam.name`) — **текущее** имя команды у STRATZ, а не имя на момент матча: у переименованных команд старые матчи показывают новое имя.
- `lane` (`MatchLaneType`) — относительно команды: `SAFE_LANE` → `SAFE`, `MID_LANE` → `MIDDLE`, `OFF_LANE` → `OFF`, `JUNGLE`, `ROAMING`; иное/null → `UNKNOWN` (`LaneMapper`).
- `position` — `POSITION_1`…`POSITION_5` → `Position` 1–5; иное/null → `null`.
- Драфт (`pickBans[]`: isPick, heroId, order 0..23, isRadiant) совпадает с OpenDota.
- Анонимный игрок (`steamAccountId = null`) → `SourceDataException` для всего матча (как у OpenDota; вне области бандла — см. «Область бандла»).

Формулы `ParsedStats` (`Stratz/Infrastructure/Match/PlayerMapper`), сверка с OpenDota на матче `9026055036`:

| Поле | Откуда | Совпадение с OpenDota |
|---|---|---|
| observers / sentries placed | число `stats.wards[]` с `type` 0 / 1 | 10/10, 8/10 (±1) |
| observers / sentries destroyed | число `stats.wardDestruction[]` с `isWard` true / false | 10/10 |
| rune pickups | число `stats.runes[]` с `action = PICKUP` | ≈ (±1–4, другая методика) |
| stun duration (сек) | `stats.heroDamageReport.dealtTotal.stunDuration / 100` (сотые доли секунды) | ≈ (другая методика) |
| camps stacked | **последний** элемент `stats.campStack` (накопительный ряд), пусто → 0 | 10/10 |
| smoke uses | сумма `count` в `stats.itemUsed[]` с `itemId = 188` | 10/10 |
| courier kills | `count(stats.courierKills)` | 10/10 |
| lane / neutral / ancient creeps | сумма `count` в `stats.farmDistributionReport.{creepLocation, neutralLocation, ancientLocation}` | lane ≈, neutral/ancient ниже |
| buybacks | `count(playbackData.buyBackEvents)`; `playbackData = null` → `null` | 10/10 |

Все вложенные чтения — через `??`: распарсенный матч с `stats = null` у игрока даёт `ParsedStats` из нулей.

**Команды**

- `team(teamId)`: рейтинга нет. Команды нет → `team = null` → «Object not found».
- `team.matches(take: 100, skip: 0)` — последние 100 матчей (у OpenDota — вся история, known-issues.md); `league` бывает `null` → `leagueName = null`; `radiantKills` / `direKills` (убийства по минутам) бывают `null` → `score = null`, иначе счёт — их суммы. Сторона команды — по `radiantTeamId` / `direTeamId`; команда не сторона матча → `SourceDataException`.
- `team.matchesGroupBy` (игроки и герои): `take` — окно матчей команды, а не число групп; по умолчанию у STRATZ оно ~100 матчей. Бандл передаёт `take: 20000` (`TeamQuery::GROUP_BY_TAKE`, литерал в запросах с ним совпадает) — это покрывает всю историю любой команды (проверено на Team Liquid, 2163 матча), поэтому matches/wins игроков и героев — за всю историю, как у OpenDota. Имя игрока — `proSteamAccount.name ?? steamAccount.name`; текущий игрок — `proSteamAccount.teamId === teamId`.
- `hero { displayName }` внутри groupBy требует `gameVersionId`, поэтому имена героев берутся из корневого `constants.heroes` в том же запросе.
- **Списка всех команд нет** (нет аналога OpenDota `/teams`).

**Фикстуры**

`tests/Fixtures/stratz/` — реальные ответы тех же запросов, что делает код, как есть (`{"data": {...}}`):

- `match_9026055036.json` — распарсенный матч (pretty-print), `match_8794744726.json` — нераспарсенный у STRATZ;
- `team_8261500.json`, `team_8261500_matches.json` (первые 20 матчей), `team_8261500_players.json`, `team_8261500_heroes.json` (с `constants`) — compact JSON.

Читает их `tests/Support/Stratz/StratzFixtures`. Обновление — `php tools/refresh-stratz-fixtures.php` (см. conventions.md → «Поля источника»).
