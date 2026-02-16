<?php

declare(strict_types=1);

namespace Dnkmdg\LocalGeoIp\Tests\Unit;

use Dnkmdg\LocalGeoIp\Contracts\GeoIpLookup as GeoIpLookupContract;
use Dnkmdg\LocalGeoIp\Data\GeoIpLocationData;
use Dnkmdg\LocalGeoIp\RequestIpResolver;
use Dnkmdg\LocalGeoIp\Testing\InteractsWithLocalGeoIp;
use Dnkmdg\LocalGeoIp\Tests\TestCase;
use Illuminate\Http\Request;

final class InteractsWithLocalGeoIpTest extends TestCase
{
    use InteractsWithLocalGeoIp;

    public function test_fake_geo_ip_binds_contract_and_returns_response(): void
    {
        $response = new GeoIpLocationData(
            ipAddress: '8.8.8.8',
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

        $this->fakeGeoIp([
            '8.8.8.8' => $response,
        ]);

        $lookup = $this->app->make(GeoIpLookupContract::class);

        $this->assertSame($response, $lookup->lookup('8.8.8.8'));
        $this->assertNull($lookup->lookup('1.1.1.1'));
    }

    public function test_fake_request_ips_binds_resolver_and_returns_fixed_candidates(): void
    {
        $this->fakeRequestIps(['198.51.100.10', '203.0.113.5']);

        $resolver = $this->app->make(RequestIpResolver::class);
        $request = Request::create('/', 'GET', server: [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '1.1.1.1, 2.2.2.2',
        ]);

        $this->assertSame(['198.51.100.10', '203.0.113.5'], $resolver->resolveCandidates($request));
    }
}
