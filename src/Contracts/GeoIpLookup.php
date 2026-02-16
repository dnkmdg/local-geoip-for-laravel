<?php

declare(strict_types=1);

namespace Dnkmdg\LocalGeoIp\Contracts;

use Dnkmdg\LocalGeoIp\Data\GeoIpLocationData;

interface GeoIpLookup
{
    public function lookup(string $ipAddress): ?GeoIpLocationData;
}
