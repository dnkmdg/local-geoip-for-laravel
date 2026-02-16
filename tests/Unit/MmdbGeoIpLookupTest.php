<?php

declare(strict_types=1);

namespace Dnkmdg\LocalGeoIp\Tests\Unit;

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Config\Repository as ConfigRepository;
use Dnkmdg\LocalGeoIp\Data\GeoIpLocationData;
use Dnkmdg\LocalGeoIp\MmdbGeoIpLookup;
use Dnkmdg\LocalGeoIp\Tests\TestCase;

final class MmdbGeoIpLookupTest extends TestCase
{
    public function test_uses_default_cache_store_when_tags_are_not_supported(): void
    {
        $fixture = dirname(__DIR__).'/Fixtures/GeoIP2-Country-Test.mmdb';
        $this->assertFileExists($fixture);

        $cache = $this->getMockBuilder(CacheRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getStore', 'tags', 'remember'])
            ->getMock();

        $cache->expects($this->once())
            ->method('getStore')
            ->willReturn(new \stdClass());

        $cache->expects($this->never())->method('tags');

        $cache->expects($this->once())
            ->method('remember')
            ->with(
                'local-geoip:lookup:2.125.160.216',
                60,
                $this->isType('callable'),
            )
            ->willReturnCallback(
                fn (string $key, int $ttl, callable $callback): ?GeoIpLocationData => $callback(),
            );

        $lookup = new MmdbGeoIpLookup(
            cache: $cache,
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
    }

    public function test_uses_tagged_cache_when_supported_by_store(): void
    {
        $fixture = dirname(__DIR__).'/Fixtures/GeoIP2-Country-Test.mmdb';
        $this->assertFileExists($fixture);

        $rememberCallCount = 0;
        $rememberedKey = null;
        $rememberedTtl = null;
        $taggedCache = new class($rememberCallCount, $rememberedKey, $rememberedTtl) {
            public function __construct(
                private int &$rememberCallCount,
                private ?string &$rememberedKey,
                private ?int &$rememberedTtl,
            ) {
            }

            public function remember(string $key, int $ttl, callable $callback): ?GeoIpLocationData
            {
                $this->rememberCallCount++;
                $this->rememberedKey = $key;
                $this->rememberedTtl = $ttl;

                return $callback();
            }
        };

        $storeWithTags = new class {
            public function tags(array $names): void
            {
            }
        };

        $cache = $this->getMockBuilder(CacheRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getStore', 'tags', 'remember'])
            ->getMock();

        $cache->expects($this->once())
            ->method('getStore')
            ->willReturn($storeWithTags);

        $cache->expects($this->once())
            ->method('tags')
            ->with(['local-geoip'])
            ->willReturn($taggedCache);

        $cache->expects($this->never())->method('remember');

        $lookup = new MmdbGeoIpLookup(
            cache: $cache,
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
        $this->assertSame(1, $rememberCallCount);
        $this->assertSame('local-geoip:lookup:2.125.160.216', $rememberedKey);
        $this->assertSame(60, $rememberedTtl);
    }

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
