<?php

namespace Histel951\Dota2Api\Domain\Providers;

interface PlayerProviderInterface
{
    /** @return PlayerHero[]
     *
     */
    public function getHeroes(PlayerId $id): array;

    /** @return PlayerPro[]
     *
     */
    public function getPros(PlayerId $id): array;

    /** @return PlayerPeer[]
     *
     */
    public function getPeers(PlayerId $id): array;

    public function getWardMap(PlayerId $id): WardMap;
}