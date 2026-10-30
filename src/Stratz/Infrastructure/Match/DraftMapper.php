<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Stratz\Infrastructure\Match;

use Histel951\Dota2Api\SharedKernel\Draft\Draft;
use Histel951\Dota2Api\SharedKernel\Draft\DraftDecision;
use Histel951\Dota2Api\SharedKernel\Draft\DraftOrder;
use Histel951\Dota2Api\SharedKernel\Exception\InvalidDraftOrderException;
use Histel951\Dota2Api\SharedKernel\Exception\InvalidHeroIdException;
use Histel951\Dota2Api\SharedKernel\HeroId;
use Histel951\Dota2Api\SharedKernel\Side;

/**
 * Собирает драфт из pickBans STRATZ
 */
final class DraftMapper
{
    /**
     * @param array<int, array<string, mixed>>|null $pickBans pickBans матча (null у нераспарсенного матча)
     *
     * @throws InvalidHeroIdException
     * @throws InvalidDraftOrderException
     */
    public function map(?array $pickBans): ?Draft
    {
        if (null === $pickBans) {
            return null;
        }

        $picks = [];
        $bans = [];

        foreach ($pickBans as $item) {
            $decision = new DraftDecision(
                heroId: new HeroId($item['heroId'] ?? null),
                draftOrder: new DraftOrder($item['order'] ?? null),
                side: ($item['isRadiant'] ?? null) ? Side::RADIANT : Side::DIRE,
                isPick: (bool) ($item['isPick'] ?? false),
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
