<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Stratz\Match\Domain\Player\Stats;

/**
 * Показатели, доступные только для распарсенного матча
 */
final readonly class ParsedStats
{
    public function __construct(
        private LaneCreeps $laneCreeps,
        private NeutralCreeps $neutralCreeps,
        private AncientCreeps $ancientCreeps,
        private CampsStacked $campsStacked,
        private ObserversPlaced $observersPlaced,
        private SentriesPlaced $sentriesPlaced,
        private ObserversDestroyed $observersDestroyed,
        private SentriesDestroyed $sentriesDestroyed,
        private RunePickups $runePickups,
        private CourierKills $courierKills,
        private SmokeUses $smokeUses,
        private StunDuration $stunDuration,
        /** null — STRATZ не отдал playbackData (не пересобран из реплея) */
        private ?Buybacks $buybacks,
    )
    {
    }

    public function getLaneCreeps(): LaneCreeps
    {
        return $this->laneCreeps;
    }

    public function getNeutralCreeps(): NeutralCreeps
    {
        return $this->neutralCreeps;
    }

    public function getAncientCreeps(): AncientCreeps
    {
        return $this->ancientCreeps;
    }

    public function getCampsStacked(): CampsStacked
    {
        return $this->campsStacked;
    }

    public function getObserversPlaced(): ObserversPlaced
    {
        return $this->observersPlaced;
    }

    public function getSentriesPlaced(): SentriesPlaced
    {
        return $this->sentriesPlaced;
    }

    public function getObserversDestroyed(): ObserversDestroyed
    {
        return $this->observersDestroyed;
    }

    public function getSentriesDestroyed(): SentriesDestroyed
    {
        return $this->sentriesDestroyed;
    }

    public function getRunePickups(): RunePickups
    {
        return $this->runePickups;
    }

    public function getCourierKills(): CourierKills
    {
        return $this->courierKills;
    }

    public function getSmokeUses(): SmokeUses
    {
        return $this->smokeUses;
    }

    public function getStunDuration(): StunDuration
    {
        return $this->stunDuration;
    }

    public function getBuybacks(): ?Buybacks
    {
        return $this->buybacks;
    }
}
