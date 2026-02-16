<?php

declare(strict_types=1);

namespace Dnkmdg\LocalGeoIp\Tests\Unit;

use Dnkmdg\LocalGeoIp\Contracts\GeoIpLookup as GeoIpLookupContract;
use Dnkmdg\LocalGeoIp\Data\GeoIpLocationData;
use Dnkmdg\LocalGeoIp\Facades\GeoIpLookup;
use Dnkmdg\LocalGeoIp\Tests\TestCase;

final class GeoIpLookupFacadeTest extends TestCase
{
    public function test_resolve_proxies_to_lookup_contract(): void
    {
        $this->app->instance(GeoIpLookupContract::class, new class implements GeoIpLookupContract {
            public function lookup(string $ipAddress): ?GeoIpLocationData
            {
                return new GeoIpLocationData(
                    ipAddress: $ipAddress,
                    countryCode: 'SE',
                    countryName: 'Sweden',
                    city: null,
                    regionCode: null,
                    regionName: null,
                    latitude: null,
                    longitude: null,
                    timeZone: null,
                    postalCode: null,
                );
            }
        });

        $result = GeoIpLookup::resolve('8.8.8.8');

        $this->assertNotNull($result);
        $this->assertSame('8.8.8.8', $result->ipAddress);
        $this->assertSame('SE', $result->countryCode);
    }
}
