<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\OpenDota\Infrastructure\Match;

final class MatchExtractor
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
        'region',
        'objectives',
    ];

    private const array PLAYER_FIELDS = [
        'account_id',
        'hero_id',
        'player_slot',
        'isRadiant',
        'lane',
        'lane_role',
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
        'teamfight_participation',
        'camps_stacked',
        'ability_uses',
        'item_usage',
        'item_uses',
        'position_est',
    ];

    /**
     * @param array $data Декодированный ответ OpenDota
     */
    /**
     * Отсутствующий ключ становится null: обязательность полей проверяет MatchMapper
     */
    public function extract(array $data): array
    {
        $players = null;
        if (is_array($data['players'] ?? null)) {
            $players = [];
            foreach ($data['players'] as $player) {
                $players[] = $this->createExtracted(is_array($player) ? $player : [], self::PLAYER_FIELDS);
            }
        }

        return [
            ...$this->createExtracted($data, self::REQUIRED_FIELDS),
            ...$this->createExtracted($data, self::OPTIONAL_FIELDS),
            'players' => $players,
        ];
    }

    private function createExtracted(array $data, array $fields): array
    {
        $result = [];
        foreach ($fields as $field) {
            $result[$field] = $data[$field] ?? null;
        }

        return $result;
    }
}