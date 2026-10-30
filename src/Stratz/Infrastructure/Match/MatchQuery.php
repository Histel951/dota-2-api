<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Stratz\Infrastructure\Match;

use Histel951\Dota2Api\SharedKernel\MatchId;

/**
 * GraphQL-запрос матчей: несколько матчей в одном запросе через алиасы m0, m1, ...
 */
final class MatchQuery
{
    /**
     * Матчей в одном запросе с playbackData: STRATZ пересобирает playbackData из реплея не более чем
     * для одного незакэшированного матча за запрос, поэтому такие запросы идут по одному матчу
     */
    public const int BATCH_SIZE_WITH_PLAYBACK = 1;

    /** Матчей в одном запросе без playbackData */
    public const int BATCH_SIZE = 5;

    /** Поля playbackData внутри FIELDS (только из них берутся байбеки); вырезаются в режиме без playbackData */
    public const string PLAYBACK_FIELDS = 'playbackData { buyBackEvents { time } }';

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
     * @param bool $withPlaybackData false — фрагмент без PLAYBACK_FIELDS (байбеки не запрашиваются)
     *
     * @return array{query: string, variables: array<string, int>}
     */
    public static function build(array $ids, bool $withPlaybackData = false): array
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
                self::fragment($withPlaybackData),
            ),
            'variables' => $variables,
        ];
    }

    /**
     * Фрагмент MatchFields: FIELDS целиком или FIELDS без PLAYBACK_FIELDS
     */
    public static function fragment(bool $withPlaybackData): string
    {
        return $withPlaybackData ? self::FIELDS : str_replace(self::PLAYBACK_FIELDS, '', self::FIELDS);
    }

    /**
     * Матчей в одном запросе для режима
     *
     * @return positive-int
     */
    public static function batchSize(bool $withPlaybackData): int
    {
        return $withPlaybackData ? self::BATCH_SIZE_WITH_PLAYBACK : self::BATCH_SIZE;
    }

    /**
     * Алиас матча с номером $index в пачке
     */
    public static function alias(int $index): string
    {
        return sprintf('m%d', $index);
    }
}
