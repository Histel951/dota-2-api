<?php
declare(strict_types = 1);

namespace Histel951\Dota2Api\Domain\Common\Enums;

enum RegionType: int
{
    case UNKNOWN = 0;

    case US_WEST = 1;
    case US_EAST = 2;
    case EUROPE = 3;

    case KOREA = 4;
    case SINGAPORE = 5;
    case DUBAI = 6;
    case AUSTRALIA = 7;

    case STOCKHOLM = 8;
    case AUSTRIA = 9;
    case BRAZIL = 10;
    case SOUTH_AFRICA = 11;

    case CHINA_TELECOM = 12;
    case CHINA_UNICOM = 13;

    case CHILE = 14;
    case PERU = 15;
    case INDIA = 16;

    case CHINA_GUANGDONG = 17;
    case CHINA_ZHEJIANG = 18;

    case JAPAN = 19;
    case CHINA_WUHAN = 20;

    case CHINA_TIANJIN = 25;

    case TAIWAN = 37;

    public function label(): string
    {
        return match ($this) {
            self::UNKNOWN => 'Unknown',

            self::US_WEST => 'US West',
            self::US_EAST => 'US East',
            self::EUROPE => 'Europe',

            self::KOREA => 'Korea',
            self::SINGAPORE => 'Singapore',
            self::DUBAI => 'Dubai',
            self::AUSTRALIA => 'Australia',

            self::STOCKHOLM => 'Stockholm',
            self::AUSTRIA => 'Austria',

            self::BRAZIL => 'Brazil',
            self::SOUTH_AFRICA => 'South Africa',

            self::CHINA_TELECOM => 'China Telecom',
            self::CHINA_UNICOM => 'China Unicom',

            self::CHILE => 'Chile',
            self::PERU => 'Peru',

            self::INDIA => 'India',

            self::CHINA_GUANGDONG => 'China Guangdong',
            self::CHINA_ZHEJIANG => 'China Zhejiang',

            self::JAPAN => 'Japan',

            self::CHINA_WUHAN => 'China Wuhan',

            self::CHINA_TIANJIN => 'China Tianjin',

            self::TAIWAN => 'Taiwan',
        };
    }

    public function code(): string
    {
        return match ($this) {
            self::UNKNOWN => 'unknown',

            self::US_WEST => 'usw',
            self::US_EAST => 'use',
            self::EUROPE => 'eu',

            self::KOREA => 'kr',
            self::SINGAPORE => 'sg',
            self::DUBAI => 'dxb',
            self::AUSTRALIA => 'au',

            self::STOCKHOLM => 'sto',
            self::AUSTRIA => 'at',

            self::BRAZIL => 'br',
            self::SOUTH_AFRICA => 'za',

            self::CHINA_TELECOM => 'cn-telecom',
            self::CHINA_UNICOM => 'cn-unicom',

            self::CHILE => 'cl',
            self::PERU => 'pe',

            self::INDIA => 'in',

            self::CHINA_GUANGDONG => 'cn-gd',
            self::CHINA_ZHEJIANG => 'cn-zj',

            self::JAPAN => 'jp',

            self::CHINA_WUHAN => 'cn-wh',

            self::CHINA_TIANJIN => 'cn-tj',

            self::TAIWAN => 'tw',
        };
    }
}