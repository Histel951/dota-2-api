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

- `Team` — TeamId, ?TeamName, ?TeamTag (пустой tag у источника → `null`), TeamStats (wins/losses/rating), lastMatchTime
- `TeamMatch` — MatchId, MatchOutcome, startTime, Cluster, LeagueId, LeagueName, opposingTeamId, ?opposingTeamName
- `TeamPlayer` — PlayerId, ?PlayerName (у бывших игроков имени нет), WinrateStats, isCurrentTeamMember (`null` у источника → `false`)
- `TeamHero` — HeroId, HeroName, WinrateStats

## Value Objects

- Почти все VO — `final readonly` (исключения: `Region`, `LeagueName`). Валидация идёт в конструкторе, при ошибке бросается свой `Invalid*Exception`. Все они наследуют `Domain\Common\Exceptions\DomainException`, кроме `InvalidRegionException` (`InvalidArgumentException`).
- `TeamId` и `TeamName` допускают `null` (матч без команды): `getValue()` возвращает `?string`.
- Счётчики статистики наследуют `Domain\Common\ValueObjects\Counter` (int ≥ 0, `getValue()`). Новый счётчик — это пустой класс `final readonly class X extends Counter {}`.

## Роли: `ProSceneRoleResolver`

OpenDota не отдаёт позицию 1–5 для про-матчей, поэтому роль выводится по линии, GPM и варданию. Резолвер работает для каждой стороны отдельно и требует ровно 5 игроков.

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
