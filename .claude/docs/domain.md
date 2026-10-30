# Доменная модель

У каждого источника своя модель (`OpenDota\…`, `Stratz\…`): она отражает то, что источник реально отдаёт, без подгонки под другой источник. Общий словарь игры — `SharedKernel`.

## SharedKernel

Только то, что одинаково по смыслу у обоих источников (проверено сверкой матча `9026055036` в OpenDota и STRATZ):

- Идентификаторы и имена: `MatchId`, `TeamId`, `TeamName`, `TeamTag`, `PlayerId`, `PlayerName`, `HeroId`, `HeroName`, `LeagueId`, `LeagueName`, `Cluster`.
- Время: `Duration` (`fromSeconds()` / `fromMinutesAndSeconds()`), `MatchStartTime` (`fromTimestamp()`).
- `Region/` — `Region::fromId(?int)` (неизвестный / null → `Region::unknown()`), enum `RegionType` (регионы Valve).
- `Side` (RADIANT=0, DIRE=1).
- Показатели: `Counter` (int ≥ 0, база всех счётчиков), `KDA` (kills/deaths/assists ≥ 0; `getCoefficient()` = `round((kills + assists) / max(1, deaths), 2)` — без смертей делитель 1, как принято в Dota: 3/0/5 → 8.0, 3/2/5 → 4.0), `GPM`, `XPM`, `WinrateStats` (`fromGames(gamesPlayed, wins)`: played/won/lost, winrate в %).
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

MatchPlayerPerformance (Player/, immutable)
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

`MatchDetail` принимает стороны, а `result` и `players` вычисляет сам в конструкторе — рассинхрона между ними не бывает. Роли приходят готовыми из маппера; мутаторов у агрегата нет.

`PlayerRole`: UNKNOWN=0, CARRY=1, MIDDLE=2, OFFLANE=3, SUPPORT=4, HARD_SUPPORT=5. `Lane` (абсолютная, со стороны Radiant): SAFE=1, MIDDLE=2, OFFLANE=3.

### Роли

Роль игрока — только `players[].position_est` OpenDota, его ставит `OpenDota/Infrastructure/Match/PlayerMapper`: значения 1–5 совпадают с `PlayerRole`, всё остальное (null, нет ключа, вне 1–5, не int) → `UNKNOWN` **у этого игрока**, матч маппится, остальные роли не трогаются. Повторы (две одинаковые роли на стороне) берутся как есть. Эвристики, резолвера и пересчёта ролей нет; `MatchService` роли не трогает, ошибок ролей не бывает.

`position_est` OpenDota отдаёт у любого распарсенного матча (проверено на 5000000000 и 7000000000, 2019–2023, и на 25 про-матчах 2026-10-30 — валиден у 50 из 50 сторон); про-матчи распарсены всегда. У нераспарсенного матча все роли — `UNKNOWN` (вне области бандла — api-coverage.md → «Область бандла»).

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
- `Position` — позиция 1–5 от STRATZ: `CARRY=1`, `MIDDLE=2`, `OFFLANE=3`, `SOFT_SUPPORT=4`, `HARD_SUPPORT=5`. Это и есть роль: эвристик и резолвера нет (как и у OpenDota). `null` — STRATZ позицию не определил (нераспарсенный матч).
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
- `Roster/TeamPlayer` — PlayerId, ?PlayerName, `stats: WinrateStats` — за **всю историю** команды (groupBy с `take: TeamQuery::GROUP_BY_TAKE = 20000`), `isCurrentMember` (`proSteamAccount.teamId` совпадает с командой).
- `Heroes/TeamHero` — HeroId, HeroName (из `constants.heroes`), `stats: WinrateStats` — тоже за всю историю команды.
- Порт (`Stratz/Team/Port/TeamSourceInterface`): `getTeam`, `getMatchesByTeam` (последние 100), `getPlayersByTeam`, `getHeroesByTeam`. Метода «все команды» нет — источник не умеет.
