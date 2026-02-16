<?php

declare(strict_types=1);

namespace Dnkmdg\LocalGeoIp\Testing;

use Dnkmdg\LocalGeoIp\Contracts\GeoIpLookup;
use Dnkmdg\LocalGeoIp\Data\GeoIpLocationData;
use Dnkmdg\LocalGeoIp\RequestIpResolver;
use Illuminate\Contracts\Container\Container;

trait InteractsWithLocalGeoIp
{
    /**
     * @param array<string, ?GeoIpLocationData> $responses
     */
    public function fakeGeoIp(array $responses): FakeGeoIpLookup
    {
        $fake = new FakeGeoIpLookup($responses);
        $this->container()->instance(GeoIpLookup::class, $fake);

        return $fake;
    }

    /**
     * @param array<int, string> $ips
     */
    public function fakeRequestIps(array $ips): FakeRequestIpResolver
    {
        $fake = new FakeRequestIpResolver($ips);
        $this->container()->instance(RequestIpResolver::class, $fake);

        return $fake;
    }

    private function container(): Container
    {
        if (property_exists($this, 'app') && $this->app instanceof Container) {
            return $this->app;
        }

        /** @var Container $container */
        $container = app();

        return $container;
    }
}
