<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Infrastructure\Provider\Extractors;

use Histel951\Dota2Api\Infrastructure\Provider\Contracts\ExtractorInterface;
use JsonException;

class MatchExtractor implements ExtractorInterface
{
    private const array REQUIRED_FIELDS = [
        'match_id',
        'duration',
        'radiant_win',
        'start_time',
    ];

    private const array OPTIONAL_FIELDS = [
        'picks_bans',
        'radiant_team_id',
        'radiant_name',
        'dire_team_id',
        'dire_name',
    ];

    private const array PLAYER_FIELDS = [
        'account_id',
        'hero_id',
        'isRadiant',
        'lane',
        'name',
        'personaname',
        'kills',
        'deaths',
        'assists',
        'roshan_kills',
        'tower_kills',
        'rune_pickups',
        'last_hits',
        'lane_kills',
        'denies',
        'neutral_kills',
        'ancient_kills',
        'net_worth',
        'total_gold',
        'total_xp',
        'gold_spent',
        'xp_per_min',
        'gold_per_min',
        'obs_placed',
        'sen_placed',
        'observer_kills',
        'sentry_kills',
        'hero_damage',
        'tower_damage',
        'hero_healing',
        'buyback_count',
        'firstblood_claimed',
        'courier_kills',
        'stuns',
        'teamfight_participation'
    ];

    /**
     * @throws JsonException
     */
    public function extract(string $rawJson): array
    {
        $data = json_decode($rawJson, true, flags: JSON_THROW_ON_ERROR);

        $players = [];
        foreach ($data['players'] as $player) {
            $players[] = $this->createExtracted($player, self::PLAYER_FIELDS);
        }

        return [
            ...$this->createExtracted($data, self::REQUIRED_FIELDS),
            ...$this->createExtracted($data, self::OPTIONAL_FIELDS, true),
            'players' => $players,
        ];
    }

    private function createExtracted(array $data, array $fields, bool $isOptional = false): array
    {
        $result = [];
        foreach ($fields as $field) {
            if ($isOptional) {
                $result[$field] = $data[$field] ?? null;
            } else {
                $result[$field] = $data[$field];
            }
        }

        return $result;
    }
}