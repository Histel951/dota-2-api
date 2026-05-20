<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Infrastructure\Provider;

use Histel951\Dota2Api\Domain\Common\Exceptions\InvalidDurationException;
use Histel951\Dota2Api\Domain\Common\Exceptions\InvalidGPMException;
use Histel951\Dota2Api\Domain\Common\Exceptions\InvalidHeroIdException;
use Histel951\Dota2Api\Domain\Common\Exceptions\InvalidKDAException;
use Histel951\Dota2Api\Domain\Common\Exceptions\InvalidMatchIdException;
use Histel951\Dota2Api\Domain\Common\Exceptions\InvalidPlayerIdException;
use Histel951\Dota2Api\Domain\Common\Exceptions\InvalidTeamIdException;
use Histel951\Dota2Api\Domain\Common\Exceptions\InvalidTeamNameException;
use Histel951\Dota2Api\Domain\Common\Exceptions\InvalidXPMException;
use Histel951\Dota2Api\Domain\Common\ValueObjects\MatchId;
use Histel951\Dota2Api\Domain\Entities\Match\MatchDetail;
use Histel951\Dota2Api\Domain\Providers\MatchesProviderInterface;
use Histel951\Dota2Api\Infrastructure\Exceptions\ApiGatewayException;
use Histel951\Dota2Api\Infrastructure\Exceptions\MappingException;
use Histel951\Dota2Api\Infrastructure\Http\Contracts\ApiGatewayInterface;
use Histel951\Dota2Api\Infrastructure\Http\ApiGateway;
use Histel951\Dota2Api\Infrastructure\Mapper\Match\MatchOpenDotaMapper;

class OpenDotaMatchProvider implements MatchesProviderInterface
{
    public function __construct(
        private readonly ApiGatewayInterface $gateway,
        private readonly MatchOpenDotaMapper $mapper
    )
    {
    }

    /**
     * @throws InvalidXPMException
     * @throws InvalidGPMException
     * @throws InvalidTeamNameException
     * @throws ApiGatewayException
     * @throws InvalidDurationException
     * @throws InvalidKDAException
     * @throws InvalidHeroIdException
     * @throws InvalidMatchIdException
     * @throws MappingException
     * @throws InvalidTeamIdException
     * @throws InvalidPlayerIdException
     */
    public function getMatch(MatchId $id): MatchDetail
    {
        $result = $this->gateway->get('matches/' . $id->getValue());
        return $this->mapper->toEntity($result);
    }
}