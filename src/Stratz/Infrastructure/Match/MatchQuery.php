<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Stratz\Infrastructure\Match;

use Histel951\Dota2Api\SharedKernel\MatchId;

/**
 * GraphQL-запрос матчей: несколько матчей в одном запросе через алиасы m0, m1, ...
 */
final class MatchQuery
{
    /** Матчей в одном запросе: playbackData STRATZ пересобирает из реплея не более одного незакэшированного матча за запрос */
    public const int BATCH_SIZE = 1;

    /** Поля матча: единственное место, из которого строятся запрос и мапперы */
    public const string FIELDS = <<<'GRAPHQL'
fragment MatchFields on MatchType {
  id didRadiantWin durationSeconds startDateTime parsedDateTime regionId
  radiantTeamId direTeamId radiantTeam { name } direTeam { name }
  pickBans { isPick heroId order isRadiant }
  players {
    steamAccountId isRadiant heroId kills deaths assists numLastHits numDenies
    goldPerMinute experiencePerMinute networth goldSpent heroDamage towerDamage heroHealing
    lane position
    steamAccount { name proSteamAccount { name } }
    stats {
      campStack wards { type } wardDestruction { isWard } runes { action } courierKills { time }
      itemUsed { itemId count }
      farmDistributionReport { creepLocation { count } neutralLocation { count } ancientLocation { count } }
      heroDamageReport { dealtTotal { stunDuration } }
    }
    playbackData { buyBackEvents { time } }
  }
}
GRAPHQL;

    /**
     * @param non-empty-list<MatchId> $ids
     *
     * @return array{query: string, variables: array<string, int>}
     */
    public static function build(array $ids): array
    {
        $declarations = [];
        $selections = [];
        $variables = [];

        foreach (array_values($ids) as $index => $id) {
            $alias = self::alias($index);
            $declarations[] = sprintf('$%s: Long!', $alias);
            $selections[] = sprintf('%1$s: match(id: $%1$s) { ...MatchFields }', $alias);
            $variables[$alias] = $id->getValue();
        }

        return [
            'query' => sprintf(
                "query Matches(%s) {\n  %s\n}\n%s",
                implode(', ', $declarations),
                implode("\n  ", $selections),
                self::FIELDS,
            ),
            'variables' => $variables,
        ];
    }

    /**
     * Алиас матча с номером $index в пачке
     */
    public static function alias(int $index): string
    {
        return sprintf('m%d', $index);
    }
}
