<?php

declare(strict_types=1);

namespace Dnkmdg\LocalGeoIp\Testing;

use Dnkmdg\LocalGeoIp\Contracts\GeoIpLookup;
use Dnkmdg\LocalGeoIp\Data\GeoIpLocationData;

final class FakeGeoIpLookup implements GeoIpLookup
{
    /**
     * @param array<string, ?GeoIpLocationData> $responses
     */
    public function __construct(
        private array $responses = [],
    ) {
    }

    public function lookup(string $ipAddress): ?GeoIpLocationData
    {
        return $this->responses[$ipAddress] ?? null;
    }

    public function set(string $ipAddress, ?GeoIpLocationData $response): self
    {
        $this->responses[$ipAddress] = $response;

        return $this;
    }
}
