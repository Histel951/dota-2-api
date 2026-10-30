<?php
declare(strict_types=1);

namespace Histel951\Dota2Api\Match\Domain;

use Histel951\Dota2Api\Match\Domain\Draft\Draft;
use Histel951\Dota2Api\Match\Domain\Player\MatchPlayers;
use Histel951\Dota2Api\Match\Domain\Region\Region;
use Histel951\Dota2Api\Match\Domain\Role\RoleResolverInterface;
use Histel951\Dota2Api\Match\Domain\Side\MatchResult;
use Histel951\Dota2Api\Match\Domain\Side\TeamSide;
use Histel951\Dota2Api\SharedKernel\Duration;
use Histel951\Dota2Api\SharedKernel\Exception\DomainException;
use Histel951\Dota2Api\SharedKernel\MatchId;

final class MatchDetail
{
    private readonly MatchResult $result;

    private readonly MatchPlayers $players;

    /**
     * Итог матча и список игроков выводятся из сторон: порядок игроков [...radiant, ...dire].
     */
    public function __construct(
        private readonly MatchId $id,
        private readonly Duration $duration,
        private readonly TeamSide $radiant,
        private readonly TeamSide $dire,
        private readonly Draft $draft,
        private readonly MatchStartTime $startTime,
        private readonly ?Region $region = null,
    ) {
        $this->result = MatchResult::fromSides($radiant, $dire);
        $this->players = new MatchPlayers([
            ...$radiant->getPlayers()->getValue(),
            ...$dire->getPlayers()->getValue(),
        ]);
    }

    /**
     * Возвращает копию матча с ролями, проставленными резолвером отдельно для каждой стороны.
     *
     * @throws DomainException
     */
    public function withResolvedRoles(RoleResolverInterface $resolver): self
    {
        $radiant = $this->radiant->withPlayers(
            new MatchPlayers($resolver->resolve($this->radiant->getPlayers()->getValue(), true)),
        );
        $dire = $this->dire->withPlayers(
            new MatchPlayers($resolver->resolve($this->dire->getPlayers()->getValue(), false)),
        );

        return new self(
            id: $this->id,
            duration: $this->duration,
            radiant: $radiant,
            dire: $dire,
            draft: $this->draft,
            startTime: $this->startTime,
            region: $this->region,
        );
    }

    public function getId(): MatchId
    {
        return $this->id;
    }

    public function getDuration(): Duration
    {
        return $this->duration;
    }

    public function getResult(): MatchResult
    {
        return $this->result;
    }

    public function getRadiant(): TeamSide
    {
        return $this->radiant;
    }

    public function getDire(): TeamSide
    {
        return $this->dire;
    }

    public function getDraft(): Draft
    {
        return $this->draft;
    }

    public function getPlayers(): MatchPlayers
    {
        return $this->players;
    }

    public function getStartTime(): MatchStartTime
    {
        return $this->startTime;
    }

    public function getRegion(): ?Region
    {
        return $this->region;
    }
}
