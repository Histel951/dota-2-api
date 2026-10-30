# Доменная модель

## Матч (`Match/Domain`)

```
MatchDetail                         — Match/Domain/MatchDetail
├─ id: MatchId, duration: Duration  — SharedKernel
├─ startTime: MatchStartTime        — Match/Domain/MatchStartTime (fromTimestamp)
├─ region: ?Region                  — Region/ (Region::fromId, enum RegionType)
├─ radiant / dire: TeamSide         — Side/ (teamId, teamName, won, draft, players, side; withPlayers())
├─ draft: Draft                     — Draft/ (picks/bans: DraftDecision[] — heroId, DraftOrder, Side, isPick)
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

Счётчики, KDA, GPM/XPM, экономика и группы статистики — в `Match/Domain/Player/Stats/`.

`MatchDetail` принимает стороны, а `result` и `players` вычисляет сам в конструкторе — рассинхрона между ними не бывает. `withResolvedRoles(RoleResolverInterface)` возвращает копию матча: radiant уходит в `resolve(..., true)`, dire — в `resolve(..., false)`, стороны пересобираются через `TeamSide::withPlayers()`. Ошибка резолвера — `DomainException`. Вызывает его только `Match/Application/MatchService`.

## Команды (`Team/Domain`)

- `Team` — TeamId, ?TeamName, ?TeamTag (пустые name/tag у источника → `null`), TeamStats (wins/losses/rating), lastMatchTime
- `History/TeamMatch` — MatchId, MatchOutcome, startTime, Cluster, LeagueId, LeagueName, opposingTeamId, ?opposingTeamName
- `Roster/TeamPlayer` — PlayerId, ?PlayerName (у бывших игроков имени нет), WinrateStats, isCurrentTeamMember (`null` у источника → `false`)
- `Heroes/TeamHero` — HeroId, `Heroes/HeroName`, WinrateStats
- `WinrateStats` (games played/won/lost, winrate) — общий для TeamPlayer и TeamHero, живёт в Team, а не в SharedKernel.

## SharedKernel

`MatchId`, `TeamId`, `TeamName`, `PlayerId`, `PlayerName`, `HeroId`, `Duration` — используются и матчем, и командами. `Exception/` — `DomainException` и `Invalid*Exception` этих VO.

## Value Objects

- Почти все VO — `final readonly` (исключения: `Region`, `LeagueName`). Валидация идёт в конструкторе, при ошибке бросается свой `Invalid*Exception`. Все они и `MatchPlayersException` наследуют `SharedKernel\Exception\DomainException`, а он реализует `Exception\Dota2ApiExceptionInterface`.
- `TeamId` и `TeamName` допускают `null` (матч без команды): `getValue()` возвращает `?string`.
- Счётчики статистики наследуют `Match/Domain/Player/Stats/Counter` (int ≥ 0, `getValue()`). Новый счётчик — это пустой класс `final readonly class X extends Counter {}`.
- Фабрики названы по смыслу, а не по источнику (конструкторы приватные): `WinrateStats::fromGames(gamesPlayed, wins)`, `TeamStats::fromRecord(wins, losses, rating)`, `MatchOutcome::fromScore(isRadiant, radiantWon, radiantScore, direScore, Duration)`, а также `Duration::fromSeconds()` / `fromMinutesAndSeconds()`, `MatchStartTime::fromTimestamp()`, `Region::fromId()`.

## Роли (`Match/Domain/Role`)

`RoleResolverInterface` → `PrefilledRoleResolver(inner: ProSceneRoleResolver)`, для каждой стороны отдельно, через `MatchDetail::withResolvedRoles()` в `MatchService`.

1. `PlayerMapper` ставит начальную роль из `players[].position_est` OpenDota: значения 1–5 совпадают с `PlayerRole`, всё остальное (null, нет ключа, вне 1–5, не int) → UNKNOWN. Ключ есть только у распарсенных матчей. Источник роли не резолвит.
2. `PrefilledRoleResolver`: если у стороны ровно 5 игроков и роли — полный набор 1–5 без повторов и UNKNOWN, роли остаются как есть. Иначе **вся сторона** уходит в `ProSceneRoleResolver` (источники внутри команды не смешиваются).

На 25 про-матчах (2026-10-30) `position_est` был валиден у 50 из 50 сторон и совпал с эвристикой у 244 из 250 игроков. Расхождения — перестановки SUPPORT ↔ HARD_SUPPORT и OFFLANE ↔ SUPPORT (эвристика отдаёт оффлейн по GPM, даже если игрок ставил сентри).

### `ProSceneRoleResolver` (запасной вариант)

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

`PlayerRole`: UNKNOWN=0, CARRY=1, MIDDLE=2, OFFLANE=3, SUPPORT=4, HARD_SUPPORT=5. `Lane`: SAFE=1, MIDDLE=2, OFFLANE=3. `Side`: RADIANT=0, DIRE=1.
