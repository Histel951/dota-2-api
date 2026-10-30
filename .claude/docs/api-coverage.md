# Покрытие API

## OpenDota

| Метод клиента | Endpoint | Результат | Маппер |
|---|---|---|---|
| `matches()->getMatch(MatchId)` | `GET matches/{id}` | `MatchFetchResult` (→ `MatchDetail`) | `MatchExtractor` + `MatchOpenDotaMapper` |
| `matches()->getMatches(MatchId[], concurrency)` | `GET matches/{id}` × N, параллельно | `Generator<MatchFetchResult>` | то же |
| `teams()->getTeams()` | `GET teams` | `Team[]` | `TeamOpenDotaMapper` |
| `teams()->getTeam(TeamId)` | `GET teams/{id}` | `Team` | `TeamOpenDotaMapper` |
| `teams()->getMatchesByTeam(TeamId)` | `GET teams/{id}/matches` | `TeamMatch[]` | `TeamMatchOpenDotaMapper` |
| `teams()->getPlayersByTeam(TeamId)` | `GET teams/{id}/players` | `TeamPlayer[]` | `TeamPlayerOpenDotaMapper` |
| `teams()->getHeroesByTeam(TeamId)` | `GET teams/{id}/heroes` | `TeamHero[]` | `TeamHeroOpenDotaMapper` |

Игроки (`/players/*`) пока не покрыты.

## STRATZ

Ещё не реализован. У STRATZ GraphQL API (`https://api.stratz.com/graphql`, Bearer-токен, без токена → 403), а не REST, как у OpenDota. `ApiGateway` заточен под REST-GET, поэтому для STRATZ понадобится свой gateway в `Infrastructure/Stratz/`.

## Особенности данных OpenDota

- `lane` — **абсолютная** позиция на карте: 1 bot, 2 mid, 3 top, 4 лес Radiant, 5 лес Dire. Значения 1–3 совпадают с `Lane::SAFE/MIDDLE/OFFLANE` со стороны Radiant.
- `lane_role` — линия **относительно команды игрока**: 1 safe, 2 mid, 3 offlane, 4 лес. Используется `MatchOpenDotaMapper::resolveLane()` только для леса (lane 4/5), с переводом в сторону Radiant.
- `is_roaming` — отдельный признак, с `lane = 5` не связан. В коде не используется.
- Для Dire safe и offlane поменяны местами — это нормализует `ProSceneRoleResolver` (параметр `$isRadiant`).
- Убийства Tormentor берутся из `objectives` (`type = CHAT_MESSAGE_MINIBOSS_KILL`, совпадение по `player_slot`).
- Smoke — `item_uses.smoke_of_deceit` (количество активаций). Watchers — `ability_uses.ability_lamp_use`.
- Лотусы: использования — `item_uses.famango` / `great_famango` / `greater_famango`. Подборы OpenDota не отдаёт.
- Про-ник игрока — `name`, при его отсутствии — `personaname`.
- Регион — `region` → `Region::fromId()` (enum `RegionType`).
- Детальная статистика (`item_uses`, `lane`, `objectives`, ...) есть только у матчей, распарсенных по реплею (`version` не null).
- `players[].position_est` — оценка позиции 1–5 от самого OpenDota, только у распарсенных матчей. Основной источник роли игрока (см. domain.md → «Роли»).
- `/teams`: у ~12% команд `tag = ""` (иногда и `name = ""`). `/teams/{id}/players`: у бывших игроков `name` и `is_current_team_member` — `null`.
- Большие числа (`dire_logo` и т.п. > `PHP_INT_MAX`) `json_decode` читает как float, поэтому дифф скрипта показывает `~ dire_logo: float -> string`. Код это поле не читает.
- Реальные ответы: `tests/Fixtures/opendota/` — `match_8794744726.json` (полный распарсенный матч), `teams.json`, `team_8261500*.json` (Xtreme Gaming). Обновляются через `php tools/refresh-opendota-fixtures.php` (см. conventions.md → «Поля источника»).
