<?php

declare(strict_types=1);

namespace Dnkmdg\LocalGeoIp\Facades;

use Dnkmdg\LocalGeoIp\Contracts\GeoIpLookup as GeoIpLookupContract;
use Dnkmdg\LocalGeoIp\Data\GeoIpLocationData;
use Illuminate\Support\Facades\Facade;

/**
 * @method static ?GeoIpLocationData lookup(string $ipAddress)
 */
final class GeoIpLookup extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return GeoIpLookupContract::class;
    }

    public static function resolve(string $ipAddress): ?GeoIpLocationData
    {
        /** @var GeoIpLookupContract|null $service */
        $service = static::getFacadeRoot();

        return $service?->lookup($ipAddress);
    }
}
