<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Stratz\Match\Domain\Player;

use Histel951\Dota2Api\Stratz\Match\Domain\Player\Stats\Damage;
use Histel951\Dota2Api\Stratz\Match\Domain\Player\Stats\Economy;
use Histel951\Dota2Api\Stratz\Match\Domain\Player\Stats\Farm;
use Histel951\Dota2Api\Stratz\Match\Domain\Player\Stats\ParsedStats;
use Histel951\Dota2Api\SharedKernel\KDA;

final readonly class MatchPlayer
{
    public function __construct(
        private Identity $identity,
        private KDA $kda,
        private Economy $economy,
        private Damage $damage,
        private Farm $farm,
        private ?ParsedStats $parsedStats,
    )
    {
    }

    public function getIdentity(): Identity
    {
        return $this->identity;
    }

    public function getKda(): KDA
    {
        return $this->kda;
    }

    public function getEconomy(): Economy
    {
        return $this->economy;
    }

    public function getDamage(): Damage
    {
        return $this->damage;
    }

    public function getFarm(): Farm
    {
        return $this->farm;
    }

    /**
     * null у нераспарсенного матча
     */
    public function getParsedStats(): ?ParsedStats
    {
        return $this->parsedStats;
    }
}
