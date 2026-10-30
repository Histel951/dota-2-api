<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\OpenDota\Infrastructure\Match;

use Histel951\Dota2Api\SharedKernel\Draft\Draft;
use Histel951\Dota2Api\SharedKernel\Draft\DraftDecision;
use Histel951\Dota2Api\SharedKernel\Draft\DraftOrder;
use Histel951\Dota2Api\SharedKernel\Exception\InvalidDraftOrderException;
use Histel951\Dota2Api\SharedKernel\Exception\InvalidHeroIdException;
use Histel951\Dota2Api\SharedKernel\HeroId;
use Histel951\Dota2Api\SharedKernel\Side;

/**
 * Собирает драфт из picks_bans OpenDota
 */
final class DraftMapper
{
    /**
     * @param array|null $picksBans picks_bans матча (null у матчей без драфта)
     *
     * @throws InvalidHeroIdException
     * @throws InvalidDraftOrderException
     */
    public function map(?array $picksBans): Draft
    {
        $picks = [];
        $bans = [];

        foreach ($picksBans ?? [] as $item) {
            $decision = new DraftDecision(
                heroId: new HeroId($item['hero_id']),
                draftOrder: new DraftOrder($item['order']),
                side: Side::from($item['team']),
                isPick: $item['is_pick'],
            );

            if ($decision->isPick()) {
                $picks[] = $decision;
            } else {
                $bans[] = $decision;
            }
        }

        return new Draft(picks: $picks, bans: $bans);
    }
}
