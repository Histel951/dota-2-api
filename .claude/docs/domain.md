# Доменная модель

## Матч

```
MatchDetail
├─ id: MatchId, duration: Duration, startTime: MatchStartTime, region: ?Region
├─ result: MatchResult (winner/loser: TeamSide)
├─ radiant / dire: TeamSide (teamId, teamName, won, draft, players, side)
├─ draft: Draft (picks/bans: DraftDecision[] — heroId, DraftOrder, Side, isPick)
└─ players: MatchPlayers (MatchPlayerPerformance[])

MatchPlayerPerformance (immutable, роль меняется только через withRole())
├─ identity:   Identity (PlayerId, HeroId, Side, MatchPlayerLane, playerProName, playerPersonaName)
├─ kda:        KDA
├─ role:       Role(PlayerRole)
├─ objectives: ObjectivesStats (RoshanKills, TowerKills, RunePickups, TormentorKills)
├─ creeps:     CreepsStats (LastHits, LaneCreeps, Denies, NeutralCreeps, AncientCreeps, CampsStacked)
├─ economy:    MatchPlayerEconomy (NetWorth, TotalGold, TotalXP, GoldSpent, XPM, GPM)
├─ warding:    WardingStats (ObserversPlaced, SentryPlaced, ObserversDestroyed, SentryDestroyed, Watchers)
├─ damage:     DamageStats (HeroDamage, TowerDamage, HeroHealing)
└─ utility:    UtilityStats (BuybackCount, firstBloodClaimed, CourierKills, Stuns, TeamfightParticipation, SmokeUses)
```

## Команды

- `Team` — TeamId, ?TeamName, ?TeamTag (пустые name/tag у источника → `null`), TeamStats (wins/losses/rating), lastMatchTime
- `TeamMatch` — MatchId, MatchOutcome, startTime, Cluster, LeagueId, LeagueName, opposingTeamId, ?opposingTeamName
- `TeamPlayer` — PlayerId, ?PlayerName (у бывших игроков имени нет), WinrateStats, isCurrentTeamMember (`null` у источника → `false`)
- `TeamHero` — HeroId, HeroName, WinrateStats

## Value Objects

- Почти все VO — `final readonly` (исключения: `Region`, `LeagueName`). Валидация идёт в конструкторе, при ошибке бросается свой `Invalid*Exception`. Все они наследуют `Domain\Common\Exceptions\DomainException`, кроме `InvalidRegionException` (`InvalidArgumentException`).
- `TeamId` и `TeamName` допускают `null` (матч без команды): `getValue()` возвращает `?string`.
- Счётчики статистики наследуют `Domain\Common\ValueObjects\Counter` (int ≥ 0, `getValue()`). Новый счётчик — это пустой класс `final readonly class X extends Counter {}`.

## Роли

`RoleResolverInterface` → `PrefilledRoleResolver(inner: ProSceneRoleResolver)`, для каждой стороны отдельно.

1. Маппер ставит начальную роль из `players[].position_est` OpenDota: значения 1–5 совпадают с `PlayerRole`, всё остальное (null, нет ключа, вне 1–5, не int) → UNKNOWN. Ключ есть только у распарсенных матчей.
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
4. Неразрешимые раскладки бросают `DomainException`.

`PlayerRole`: UNKNOWN=0, CARRY=1, MIDDLE=2, OFFLANE=3, SUPPORT=4, HARD_SUPPORT=5. `Lane`: SAFE=1, MIDDLE=2, OFFLANE=3. `Side`: RADIANT=0, DIRE=1.
