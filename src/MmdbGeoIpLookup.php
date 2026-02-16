<?php

declare(strict_types=1);

namespace Dnkmdg\LocalGeoIp;

use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;
use BadMethodCallException;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Config\Repository as ConfigRepository;
use Psr\Log\LoggerInterface;
use Dnkmdg\LocalGeoIp\Contracts\GeoIpLookup;
use Dnkmdg\LocalGeoIp\Data\GeoIpLocationData;
use Throwable;

final class MmdbGeoIpLookup implements GeoIpLookup
{
    private bool $staleWarningEmitted = false;
    private ?Reader $reader = null;
    private ?string $readerPath = null;

    public function __construct(
        private readonly CacheRepository $cache,
        private readonly ConfigRepository $config,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function lookup(string $ipAddress): ?GeoIpLocationData
    {
        if (! $this->isPublicIp($ipAddress)) {
            return null;
        }

        $databasePath = (string) $this->config->get('local-geoip.database_path', '');
        if ($databasePath === '' || ! is_file($databasePath)) {
            return null;
        }

        $this->emitStaleWarningIfNeeded($databasePath);

        $ttl = (int) $this->config->get('local-geoip.cache_ttl', 86400);
        $key = sprintf('local-geoip:lookup:%s', $ipAddress);

        $resolver = fn (): ?GeoIpLocationData => $this->readFromDatabase($databasePath, $ipAddress);

        if (method_exists($this->cache->getStore(), 'tags')) {
            return $this->cache->tags(['local-geoip'])->remember($key, $ttl, $resolver);
        }

        return $this->cache->remember($key, $ttl, $resolver);
    }

    private function isPublicIp(string $ipAddress): bool
    {
        return filter_var(
            $ipAddress,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }

    private function emitStaleWarningIfNeeded(string $databasePath): void
    {
        if ($this->staleWarningEmitted) {
            return;
        }

        $maxAgeDays = (int) $this->config->get('local-geoip.database_max_age_days', 45);
        if ($maxAgeDays <= 0) {
            return;
        }

        $modifiedAt = @filemtime($databasePath);
        if ($modifiedAt === false) {
            return;
        }

        $ageSeconds = time() - $modifiedAt;
        if ($ageSeconds <= ($maxAgeDays * 86400)) {
            return;
        }

        $this->staleWarningEmitted = true;
        $this->logger?->warning('GeoIP MMDB appears stale.', [
            'database_path' => $databasePath,
            'database_max_age_days' => $maxAgeDays,
            'last_modified_at' => date(DATE_ATOM, $modifiedAt),
        ]);
    }

    private function readFromDatabase(string $databasePath, string $ipAddress): ?GeoIpLocationData
    {
        $reader = $this->reader($databasePath);
        if ($reader === null) {
            return null;
        }

        try {
            $record = $reader->city($ipAddress);
            $subdivision = $record->mostSpecificSubdivision;

            return new GeoIpLocationData(
                ipAddress: $ipAddress,
                countryCode: $record->country->isoCode,
                countryName: $record->country->name,
                city: $record->city->name,
                regionCode: $subdivision->isoCode,
                regionName: $subdivision->name,
                latitude: $record->location->latitude,
                longitude: $record->location->longitude,
                timeZone: $record->location->timeZone,
                postalCode: $record->postal->code,
            );
        } catch (BadMethodCallException) {
            return $this->readCountryRecord($reader, $ipAddress, $databasePath);
        } catch (AddressNotFoundException) {
            return null;
        } catch (Throwable $e) {
            $this->logger?->warning('GeoIP MMDB read failed.', [
                'database_path' => $databasePath,
                'ip_address' => $ipAddress,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function __destruct()
    {
        $this->reader?->close();
    }

    private function reader(string $databasePath): ?Reader
    {
        if ($this->reader !== null && $this->readerPath === $databasePath) {
            return $this->reader;
        }

        $this->reader?->close();
        $this->reader = null;
        $this->readerPath = null;

        try {
            $this->reader = new Reader($databasePath);
            $this->readerPath = $databasePath;

            return $this->reader;
        } catch (Throwable $e) {
            $this->logger?->warning('Unable to open GeoIP MMDB.', [
                'database_path' => $databasePath,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function readCountryRecord(Reader $reader, string $ipAddress, string $databasePath): ?GeoIpLocationData
    {
        try {
            $record = $reader->country($ipAddress);

            return new GeoIpLocationData(
                ipAddress: $ipAddress,
                countryCode: $record->country->isoCode,
                countryName: $record->country->name,
                city: null,
                regionCode: null,
                regionName: null,
                latitude: null,
                longitude: null,
                timeZone: null,
                postalCode: null,
            );
        } catch (AddressNotFoundException) {
            return null;
        } catch (Throwable $e) {
            $this->logger?->warning('GeoIP country MMDB read failed.', [
                'database_path' => $databasePath,
                'ip_address' => $ipAddress,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
