<?php

declare(strict_types=1);

namespace Dnkmdg\LocalGeoIp\Commands;

use GeoIp2\Database\Reader;
use Illuminate\Console\Command;
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

    public function handle(): int
    {
        $updateConfig = (array) config('string-ip-lookup.update', []);
        $targetPath = (string) config('string-ip-lookup.database_path', '');

        if (! (bool) ($updateConfig['enabled'] ?? false)) {
            $this->error('GeoIP MMDB update is disabled (string-ip-lookup.update.enabled=false).');

            return self::FAILURE;
        }

        $accountId = trim((string) ($updateConfig['account_id'] ?? ''));
        $licenseKey = trim((string) ($updateConfig['license_key'] ?? ''));
        $editionId = trim((string) ($updateConfig['edition_id'] ?? 'GeoLite2-City'));
        $downloadUrl = trim((string) ($updateConfig['download_url'] ?? 'https://download.maxmind.com/app/geoip_download'));

        if ($accountId === '' || $licenseKey === '' || $editionId === '' || $downloadUrl === '' || $targetPath === '') {
            $this->error('Missing required MMDB update configuration values.');

            return self::FAILURE;
        }

        $workDir = storage_path('app/geoip/tmp/'.uniqid('update_', true));
        File::ensureDirectoryExists($workDir);

        $archivePath = $workDir.'/mmdb.tar.gz';
        $tarPath = $workDir.'/mmdb.tar';
        $extractDir = $workDir.'/extract';

        try {
            $response = Http::withBasicAuth($accountId, $licenseKey)
                ->timeout(120)
                ->sink($archivePath)
                ->get($downloadUrl, [
                    'edition_id' => $editionId,
                    'suffix' => 'tar.gz',
                ]);

            if (! $response->successful()) {
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
                $this->error('Downloaded MMDB validation failed.');

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
                $this->error('Temporary MMDB validation failed.');

                return self::FAILURE;
            }

            if (! @rename($tmpTarget, $targetPath)) {
                @unlink($tmpTarget);
                $this->error('Atomic MMDB replace failed.');

                return self::FAILURE;
            }

            $this->info(sprintf('MMDB updated successfully: %s', $targetPath));

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error(sprintf('MMDB update failed: %s', $e->getMessage()));

            return self::FAILURE;
        } finally {
            $this->cleanupDirectory($workDir);
        }
    }

    private function validateMmdb(string $path): bool
    {
        try {
            $reader = new Reader($path);
            $reader->city('8.8.8.8');
            $reader->close();

            return true;
        } catch (Throwable) {
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
