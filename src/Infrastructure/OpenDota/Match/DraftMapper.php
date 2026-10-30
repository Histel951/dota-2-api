<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Infrastructure\OpenDota\Match;

use Histel951\Dota2Api\Match\Domain\Draft\Draft;
use Histel951\Dota2Api\Match\Domain\Draft\DraftDecision;
use Histel951\Dota2Api\Match\Domain\Draft\DraftOrder;
use Histel951\Dota2Api\Match\Domain\Exception\InvalidDraftOrderException;
use Histel951\Dota2Api\Match\Domain\Side\Side;
use Histel951\Dota2Api\SharedKernel\Exception\InvalidHeroIdException;
use Histel951\Dota2Api\SharedKernel\HeroId;

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
