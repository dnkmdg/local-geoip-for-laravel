<?php

declare(strict_types=1);

namespace Dnkmdg\LocalGeoIp\Data;

final class GeoIpLocationData
{
    public function __construct(
        public readonly string $ipAddress,
        public readonly ?string $countryCode,
        public readonly ?string $countryName,
        public readonly ?string $city,
        public readonly ?string $regionCode,
        public readonly ?string $regionName,
        public readonly ?float $latitude,
        public readonly ?float $longitude,
        public readonly ?string $timeZone,
        public readonly ?string $postalCode,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ipAddress' => $this->ipAddress,
            'countryCode' => $this->countryCode,
            'countryName' => $this->countryName,
            'city' => $this->city,
            'regionCode' => $this->regionCode,
            'regionName' => $this->regionName,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'timeZone' => $this->timeZone,
            'postalCode' => $this->postalCode,
        ];
    }
}
