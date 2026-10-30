# Доменная модель

У каждого источника своя модель (`OpenDota\…`, `Stratz\…`): она отражает то, что источник реально отдаёт, без подгонки под другой источник. Общий словарь игры — `SharedKernel`.

## SharedKernel

Только то, что одинаково по смыслу у обоих источников (проверено сверкой матча `9026055036` в OpenDota и STRATZ):

- Идентификаторы и имена: `MatchId`, `TeamId`, `TeamName`, `TeamTag`, `PlayerId`, `PlayerName`, `HeroId`, `HeroName`, `LeagueId`, `LeagueName`, `Cluster`.
- Время: `Duration` (`fromSeconds()` / `fromMinutesAndSeconds()`), `MatchStartTime` (`fromTimestamp()`).
- `Region/` — `Region::fromId(?int)` (неизвестный / null → `Region::unknown()`), enum `RegionType` (регионы Valve).
- `Side` (RADIANT=0, DIRE=1).
- Показатели: `Counter` (int ≥ 0, база всех счётчиков), `KDA`, `GPM`, `XPM`, `WinrateStats` (`fromGames(gamesPlayed, wins)`: played/won/lost, winrate в %).
- `Draft/` — `Draft` (picks/bans), `DraftDecision` (heroId, `DraftOrder`, `Side`, isPick).
- `Exception/` — `DomainException` и `Invalid*Exception` этих VO.

Линия, роль/позиция, статистика игрока, команда, история матчей команды — **у каждого источника свои** (у источников они разные по смыслу или по составу).

## Value Objects (общие правила)

- Почти все VO — `final readonly` (исключения: `Region`, `LeagueName`). Валидация — в конструкторе, при ошибке — свой `Invalid*Exception`. Все доменные исключения наследуют `SharedKernel\Exception\DomainException`, а он реализует `Exception\Dota2ApiExceptionInterface`.
- `TeamId` и `TeamName` допускают `null` (матч без команды): `getValue()` возвращает `?…`.
- Счётчик статистики — пустой маркер `final readonly class X extends SharedKernel\Counter {}` в `<Source>/Match/Domain/Player/Stats/`.
- Фабрики названы по смыслу, а не по источнику (конструкторы приватные): `WinrateStats::fromGames()`, `TeamStats::fromRecord()`, `MatchOutcome::fromScore()`, `Duration::fromSeconds()`, `MatchStartTime::fromTimestamp()`, `Region::fromId()`.

---

## OpenDota — матч (`OpenDota/Match/Domain`)

```
MatchDetail                         — OpenDota/Match/Domain/MatchDetail
├─ id: MatchId, duration: Duration, startTime: MatchStartTime — SharedKernel
├─ region: ?Region                  — SharedKernel/Region
├─ radiant / dire: TeamSide         — Side/ (teamId, teamName, won, draft, players, side; withPlayers())
├─ draft: Draft                     — SharedKernel/Draft (picks/bans: DraftDecision[])
├─ result: MatchResult              — Side/, выводится из сторон: MatchResult::fromSides(radiant, dire) по isWon()
└─ players: MatchPlayers            — Player/, выводится из сторон: [...radiant, ...dire]

MatchPlayerPerformance (Player/, immutable, роль меняется только через withRole())
├─ identity:   Identity (PlayerId, HeroId, Side, MatchPlayerLane(Lane), playerProName, playerPersonaName)
├─ kda:        KDA
├─ role:       Role(PlayerRole)
├─ objectives: ObjectivesStats (RoshanKills, TowerKills, RunePickups, TormentorKills)
├─ creeps:     CreepsStats (LastHits, LaneCreeps, Denies, NeutralCreeps, AncientCreeps, CampsStacked)
├─ economy:    MatchPlayerEconomy (NetWorth, TotalGold, TotalXP, GoldSpent, XPM, GPM)
├─ warding:    WardingStats (ObserversPlaced, SentryPlaced, ObserversDestroyed, SentryDestroyed, Watchers)
├─ damage:     DamageStats (HeroDamage, TowerDamage, HeroHealing)
└─ utility:    UtilityStats (BuybackCount, firstBloodClaimed, CourierKills, Stuns, TeamfightParticipation, SmokeUses)
```

Счётчики и группы статистики — в `OpenDota/Match/Domain/Player/Stats/`; `KDA`, `GPM`, `XPM`, `Counter` — из `SharedKernel`.

`MatchDetail` принимает стороны, а `result` и `players` вычисляет сам в конструкторе — рассинхрона между ними не бывает. `withResolvedRoles(RoleResolverInterface)` возвращает копию матча: radiant уходит в `resolve(..., true)`, dire — в `resolve(..., false)`, стороны пересобираются через `TeamSide::withPlayers()`. Ошибка резолвера — `DomainException`. Вызывает его только `OpenDota/Match/Application/MatchService`. Исключение контекста — `Exception/MatchPlayersException`.

`PlayerRole`: UNKNOWN=0, CARRY=1, MIDDLE=2, OFFLANE=3, SUPPORT=4, HARD_SUPPORT=5. `Lane` (абсолютная, со стороны Radiant): SAFE=1, MIDDLE=2, OFFLANE=3.

### Роли (`OpenDota/Match/Domain/Role`)

`RoleResolverInterface` → `PrefilledRoleResolver(inner: ProSceneRoleResolver)`, для каждой стороны отдельно, через `MatchDetail::withResolvedRoles()` в `MatchService`.

1. `PlayerMapper` ставит начальную роль из `players[].position_est` OpenDota: значения 1–5 совпадают с `PlayerRole`, всё остальное (null, нет ключа, вне 1–5, не int) → UNKNOWN. Ключ есть только у распарсенных матчей. Источник роли не резолвит.
2. `PrefilledRoleResolver`: если у стороны ровно 5 игроков и роли — полный набор 1–5 без повторов и UNKNOWN, роли остаются как есть. Иначе **вся сторона** уходит в `ProSceneRoleResolver` (источники внутри команды не смешиваются).

На 25 про-матчах (2026-10-30) `position_est` был валиден у 50 из 50 сторон и совпал с эвристикой у 244 из 250 игроков. Расхождения — перестановки SUPPORT ↔ HARD_SUPPORT и OFFLANE ↔ SUPPORT (эвристика отдаёт оффлейн по GPM, даже если игрок ставил сентри).

#### `ProSceneRoleResolver` (запасной вариант)

Роль выводится по линии, GPM и варданию. Резолвер работает для каждой стороны отдельно и требует ровно 5 игроков.

1. Для Dire линия нормализуется: SAFE ↔ OFFLANE.
2. Mid:
   - 1 игрок → MIDDLE.
   - 2 игрока → больший GPM получает MIDDLE, второй становится HARD_SUPPORT (если на safe не 2 игрока) или SUPPORT (если на offlane не 2).
   - 3 игрока → больший GPM получает MIDDLE, двое других — саппорты по sentry (только если на каждой из остальных линий по 1 игроку).
3. Safe (CARRY / HARD_SUPPORT) и Offlane (OFFLANE / SUPPORT):
   - 1 игрок → core-роль.
   - 2 игрока → core по большему GPM.
   - 3 и больше → core по GPM, остальные — HARD_SUPPORT и SUPPORT по количеству sentry.
4. Неразрешимые раскладки бросают `DomainException` — в `MatchFetchResult::getError()` он попадает как есть.

## OpenDota — команды (`OpenDota/Team/Domain`)

- `Team` — TeamId, ?TeamName, ?TeamTag (пустые name/tag у источника → `null`), `TeamStats` (wins/losses/rating, `fromRecord()`), lastMatchTime.
- `History/TeamMatch` — MatchId, `MatchOutcome` (`fromScore(isRadiant, radiantWon, radiantScore, direScore, Duration)`), startTime, Cluster, LeagueId, LeagueName, opposingTeamId, ?opposingTeamName.
- `Roster/TeamPlayer` — PlayerId, ?PlayerName (у бывших игроков имени нет), WinrateStats, isCurrentTeamMember (`null` у источника → `false`).
- `Heroes/TeamHero` — HeroId, HeroName, WinrateStats.
- Исключение контекста — `Exception/InvalidTeamStatsException`.

---

## STRATZ — матч (`Stratz/Match/Domain`)

```
MatchDetail                         — Stratz/Match/Domain/MatchDetail
├─ id: MatchId, duration: Duration, startTime: MatchStartTime — SharedKernel
├─ region: Region                   — SharedKernel/Region (нет regionId → Region::unknown())
├─ radiant / dire: TeamSide         — TeamSide (side, teamId, teamName — текущее имя команды у STRATZ, players)
├─ winner: Side                     — getWinner(): TeamSide
├─ draft: ?Draft                    — SharedKernel/Draft; null у нераспарсенного матча
├─ isParsed: bool                   — STRATZ распарсил реплей (parsedDateTime не null)
└─ getPlayers(): list<MatchPlayer>  — [...radiant, ...dire]

MatchPlayer (Player/)
├─ identity:    Identity (PlayerId, HeroId, Side, Lane, ?Position, ?proName, ?personaName)
├─ kda:         KDA
├─ economy:     Economy (GPM, XPM, NetWorth, GoldSpent)
├─ damage:      Damage (HeroDamage, TowerDamage, HeroHealing)
├─ farm:        Farm (LastHits, Denies)
└─ parsedStats: ?ParsedStats — null у нераспарсенного матча
     LaneCreeps, NeutralCreeps, AncientCreeps, CampsStacked,
     ObserversPlaced, SentriesPlaced, ObserversDestroyed, SentriesDestroyed,
     RunePickups, CourierKills, SmokeUses, StunDuration (float секунд, ≥ 0), ?Buybacks
```

- `MatchDetail` проверяет, что `radiant` — сторона Radiant, а `dire` — Dire; `TeamSide` — что все её игроки с той же стороны. Нарушение → `Exception/InvalidMatchSideException`. `StunDuration < 0` → `Exception/InvalidStunDurationException`.
- `Lane` — линия **относительно команды игрока** (как отдаёт STRATZ): `SAFE`, `MIDDLE`, `OFF`, `JUNGLE`, `ROAMING`, `UNKNOWN` (нераспарсенный матч / неизвестное значение). В абсолютную не переводится.
- `Position` — позиция 1–5 от STRATZ: `CARRY=1`, `MIDDLE=2`, `OFFLANE=3`, `SOFT_SUPPORT=4`, `HARD_SUPPORT=5`. Это и есть роль: эвристики, резолвера и `withRole()` у STRATZ нет. `null` — STRATZ позицию не определил (нераспарсенный матч).
- `proName` — `steamAccount.proSteamAccount.name`, `personaName` — `steamAccount.name`; пустое → `null`.
- `ParsedStats::getBuybacks()` — `null`, если STRATZ не отдал `playbackData` (не пересобрал его из реплея), даже у распарсенного матча. Пустой список событий → `Buybacks(0)`.
- Счётчики-маркеры в `Player/Stats/`: NetWorth, GoldSpent, HeroDamage, TowerDamage, HeroHealing, LastHits, Denies, LaneCreeps, NeutralCreeps, AncientCreeps, CampsStacked, ObserversPlaced, SentriesPlaced, ObserversDestroyed, SentriesDestroyed, RunePickups, CourierKills, SmokeUses, Buybacks.

**Чего в модели нет и почему:**

- roshan / tower / tormentor kills, watchers — STRATZ не привязывает их к игроку. Полей нет совсем (не нули), чтобы «0» не выдавался за реальное значение.
- total gold / total XP — выводятся из GPM/XPM и длительности, источник их отдельно не отдаёт.
- first blood, teamfight participation — вычисляемые по событиям/убийствам стороны; потребителя пока нет (YAGNI).
- `MatchResult`, `MatchPlayers`, резолвер ролей — не нужны: победитель — `winner`, игроки — список, роль — `Position`.

Формулы, по которым мапперы считают `ParsedStats`, — api-coverage.md → «Особенности данных STRATZ».

## STRATZ — команды (`Stratz/Team/Domain`)

- `Team` — TeamId, ?TeamName, ?TeamTag (пустые → `null`), `record: WinrateStats` (winCount + lossCount, winCount), lastMatchTime. **Рейтинга нет** — STRATZ его не отдаёт.
- `History/TeamMatch` — MatchId, side (`Side`, за кого играла команда), won, `?Score`, startTime, Duration, Cluster, LeagueId, `?LeagueName` (`league` у STRATZ бывает `null` / пустой displayName), opposingTeamId, ?opposingTeamName.
- `History/Score` — radiantKills, direKills (≥ 0, иначе `Exception/InvalidScoreException`). `null`, если STRATZ не отдал убийства по минутам (`radiantKills` / `direKills` = null).
- `Roster/TeamPlayer` — PlayerId, ?PlayerName, `stats: WinrateStats` — **по окну последних матчей STRATZ** (groupBy, ~100 матчей), а не за всю историю, `isCurrentMember` (`proSteamAccount.teamId` совпадает с командой).
- `Heroes/TeamHero` — HeroId, HeroName (из `constants.heroes`), `stats: WinrateStats` по тому же окну.
- Порт (`Stratz/Team/Port/TeamSourceInterface`): `getTeam`, `getMatchesByTeam` (последние 100), `getPlayersByTeam`, `getHeroesByTeam`. Метода «все команды» нет — источник не умеет.
