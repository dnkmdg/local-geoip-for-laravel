<?php

declare(strict_types=1);

namespace Dnkmdg\LocalGeoIp\Commands;

use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;
use BadMethodCallException;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use PharData;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

final class UpdateMmdbCommand extends Command
{
    protected $signature = 'geoip:update-mmdb';

    protected $description = 'Download and atomically update the local MaxMind MMDB database';
    private ?string $lastValidationError = null;

    public function handle(): int
    {
        $updateConfig = (array) config('local-geoip.update', []);
        $targetPath = (string) config('local-geoip.database_path', '');

        $accountId = trim((string) ($updateConfig['account_id'] ?? ''));
        $licenseKey = trim((string) ($updateConfig['license_key'] ?? ''));
        $editionId = trim((string) ($updateConfig['edition_id'] ?? 'GeoLite2-Country'));
        $downloadUrl = trim((string) ($updateConfig['download_url'] ?? 'https://download.maxmind.com/geoip/databases/{edition_id}/download'));
        $resolvedDownloadUrl = str_replace('{edition_id}', rawurlencode($editionId), $downloadUrl);

        if ($accountId === '' || $licenseKey === '' || $editionId === '' || $downloadUrl === '' || $resolvedDownloadUrl === '' || $targetPath === '') {
            $this->error('Missing required MMDB update configuration values.');

            return self::FAILURE;
        }

        $workDir = storage_path('app/geoip/tmp/'.uniqid('update_', true));
        File::ensureDirectoryExists($workDir);

        $archivePath = $workDir.'/mmdb.tar.gz';
        $tarPath = $workDir.'/mmdb.tar';
        $extractDir = $workDir.'/extract';

        try {
            $query = ['suffix' => 'tar.gz'];
            // Backward compatibility for legacy MaxMind endpoint.
            if (! str_contains($resolvedDownloadUrl, '{edition_id}') && str_contains($downloadUrl, 'app/geoip_download')) {
                $query['edition_id'] = $editionId;
            }

            $response = Http::withBasicAuth($accountId, $licenseKey)
                ->withOptions([
                    // MaxMind download endpoints redirect to R2 presigned URLs.
                    'allow_redirects' => [
                        'max' => 10,
                        'strict' => true,
                    ],
                ])
                ->timeout(120)
                ->sink($archivePath)
                ->get($resolvedDownloadUrl, $query);

            if (! $response->successful()) {
                if ($response->status() === 401) {
                    $this->error('MMDB download unauthorized (HTTP 401).');
                    $this->line('Check LOCAL_GEOIP_UPDATE_ACCOUNT_ID and LOCAL_GEOIP_UPDATE_LICENSE_KEY (or legacy MAXMIND_ACCOUNT_ID/MAXMIND_LICENSE_KEY), ensure the key has GeoLite download access, and clear config cache (php artisan config:clear).');

                    return self::FAILURE;
                }

                if ($response->status() === 403) {
                    $this->error('MMDB download forbidden (HTTP 403).');
                    $this->line('Check license entitlement/subscription status and confirm requests to mm-prod-geoip-databases.a2649acb697e2c09b632799562c076f2.r2.cloudflarestorage.com are allowed by proxy/firewall.');

                    return self::FAILURE;
                }

                $this->error(sprintf('MMDB download failed with HTTP %s.', $response->status()));

                return self::FAILURE;
            }

            if (! is_file($archivePath) || filesize($archivePath) < 1024) {
                $this->error('Downloaded archive is missing or too small.');

                return self::FAILURE;
            }

            $phar = new PharData($archivePath);
            $phar->decompress();

            $tar = new PharData($tarPath);
            $tar->extractTo($extractDir, null, true);

            $mmdbPath = $this->findMmdbFile($extractDir, sprintf('%s.mmdb', $editionId));
            if ($mmdbPath === null) {
                $this->error('Could not find MMDB file in extracted archive.');

                return self::FAILURE;
            }

            if (! $this->validateMmdb($mmdbPath)) {
                $this->error(sprintf('Downloaded MMDB validation failed%s.', $this->lastValidationError ? ': '.$this->lastValidationError : ''));

                return self::FAILURE;
            }

            $targetDirectory = dirname($targetPath);
            File::ensureDirectoryExists($targetDirectory);

            $tmpTarget = $targetPath.'.tmp';
            if (! copy($mmdbPath, $tmpTarget)) {
                $this->error('Failed to copy MMDB to temporary target path.');

                return self::FAILURE;
            }

            if (! $this->validateMmdb($tmpTarget)) {
                @unlink($tmpTarget);
                $this->error(sprintf('Temporary MMDB validation failed%s.', $this->lastValidationError ? ': '.$this->lastValidationError : ''));

                return self::FAILURE;
            }

            if (! @rename($tmpTarget, $targetPath)) {
                @unlink($tmpTarget);
                $this->error('Atomic MMDB replace failed.');

                return self::FAILURE;
            }

            $this->info(sprintf('MMDB updated successfully: %s', $targetPath));

            return self::SUCCESS;
        } catch (ConnectionException $e) {
            $this->error('MMDB download connection failed.');
            $this->line('Ensure outbound HTTPS and DNS work and that proxy/firewall allows redirects to mm-prod-geoip-databases.a2649acb697e2c09b632799562c076f2.r2.cloudflarestorage.com.');
            $this->line(sprintf('Connection error: %s', $e->getMessage()));

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error(sprintf('MMDB update failed: %s', $e->getMessage()));

            return self::FAILURE;
        } finally {
            $this->cleanupDirectory($workDir);
        }
    }

    private function validateMmdb(string $path): bool
    {
        $this->lastValidationError = null;

        try {
            $reader = new Reader($path);
            try {
                $reader->city('8.8.8.8');
            } catch (BadMethodCallException) {
                try {
                    $reader->country('8.8.8.8');
                } catch (AddressNotFoundException) {
                    // Database is valid even if this probe IP is absent.
                }
            } catch (AddressNotFoundException) {
                // Database is valid even if this probe IP is absent.
            }
            $reader->close();

            return true;
        } catch (Throwable $e) {
            $this->lastValidationError = $e->getMessage();

            return false;
        }
    }

    private function findMmdbFile(string $directory, string $targetFilename): ?string
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

        foreach ($iterator as $fileInfo) {
            if (! $fileInfo->isFile()) {
                continue;
            }

            if ($fileInfo->getFilename() === $targetFilename) {
                return $fileInfo->getPathname();
            }
        }

        return null;
    }

    private function cleanupDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        File::deleteDirectory($directory);
    }
}
