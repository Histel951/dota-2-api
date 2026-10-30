<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Stratz\Infrastructure\Team;

/**
 * GraphQL-запросы команды; переменная — ['teamId' => int]
 */
final class TeamQuery
{
    /** Сколько последних матчей команды запрашивается */
    public const int MATCHES_TAKE = 100;

    public const string TEAM = <<<'GRAPHQL'
query Team($teamId: Int!) {
  team(teamId: $teamId) { id name tag winCount lossCount lastMatchDateTime }
}
GRAPHQL;

    public const string MATCHES = <<<'GRAPHQL'
query TeamMatches($teamId: Int!) {
  team(teamId: $teamId) {
    matches(request: {take: 100, skip: 0}) {
      id didRadiantWin durationSeconds startDateTime clusterId leagueId league { displayName }
      radiantTeamId direTeamId radiantTeam { name } direTeam { name } radiantKills direKills
    }
  }
}
GRAPHQL;

    public const string PLAYERS = <<<'GRAPHQL'
query TeamPlayers($teamId: Int!) {
  team(teamId: $teamId) {
    players: matchesGroupBy(request: {playerList: SINGLE, groupBy: STEAM_ACCOUNT_ID, take: 100}) {
      ... on MatchGroupBySteamAccountIdType {
        steamAccountId matchCount winCount steamAccount { name proSteamAccount { name teamId } }
      }
    }
  }
}
GRAPHQL;

    public const string HEROES = <<<'GRAPHQL'
query TeamHeroes($teamId: Int!) {
  team(teamId: $teamId) {
    heroes: matchesGroupBy(request: {playerList: SINGLE, groupBy: HERO, take: 200}) {
      ... on MatchGroupByHeroType { heroId matchCount winCount }
    }
  }
  constants { heroes { id displayName } }
}
GRAPHQL;
}
