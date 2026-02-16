<?php

declare(strict_types=1);

namespace Dnkmdg\LocalGeoIp\Tests\Unit;

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Config\Repository as ConfigRepository;
use Dnkmdg\LocalGeoIp\MmdbGeoIpLookup;
use Dnkmdg\LocalGeoIp\Tests\TestCase;

final class MmdbGeoIpLookupTest extends TestCase
{
    public function test_invalid_private_or_reserved_ip_returns_null(): void
    {
        $lookup = new MmdbGeoIpLookup(
            cache: new CacheRepository(new ArrayStore()),
            config: new ConfigRepository([
                'local-geoip' => [
                    'database_path' => '/tmp/does-not-matter.mmdb',
                ],
            ]),
        );

        $this->assertNull($lookup->lookup('not-an-ip'));
        $this->assertNull($lookup->lookup('10.0.0.1'));
        $this->assertNull($lookup->lookup('127.0.0.1'));
        $this->assertNull($lookup->lookup('0.0.0.0'));
    }

    public function test_missing_database_returns_null_gracefully(): void
    {
        $lookup = new MmdbGeoIpLookup(
            cache: new CacheRepository(new ArrayStore()),
            config: new ConfigRepository([
                'local-geoip' => [
                    'database_path' => '/tmp/missing-db.mmdb',
                    'cache_ttl' => 60,
                ],
            ]),
        );

        $this->assertNull($lookup->lookup('8.8.8.8'));
    }

    public function test_country_database_fixture_returns_expected_country(): void
    {
        $fixture = dirname(__DIR__).'/Fixtures/GeoIP2-Country-Test.mmdb';
        $this->assertFileExists($fixture);

        $lookup = new MmdbGeoIpLookup(
            cache: new CacheRepository(new ArrayStore()),
            config: new ConfigRepository([
                'local-geoip' => [
                    'database_path' => $fixture,
                    'cache_ttl' => 60,
                ],
            ]),
        );

        $result = $lookup->lookup('2.125.160.216');

        $this->assertNotNull($result);
        $this->assertSame('GB', $result->countryCode);
        $this->assertSame('United Kingdom', $result->countryName);
        $this->assertNull($result->city);
        $this->assertNull($result->latitude);
    }
}
