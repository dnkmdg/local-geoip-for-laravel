<?php

declare(strict_types=1);

namespace Dnkmdg\LocalGeoIp\Commands;

use Illuminate\Console\Command;

final class InstallCommand extends Command
{
    protected $signature = 'geoip:install {--no-download : Skip the initial MMDB download prompt}';

    protected $description = 'Install String IP Lookup config and optionally download the first MMDB file';

    public function handle(): int
    {
        $this->call('vendor:publish', [
            '--tag' => 'local-geoip-config',
        ]);

        if ((bool) $this->option('no-download')) {
            $this->info('Install completed. Initial MMDB download skipped.');

            return self::SUCCESS;
        }

        if (! $this->input->isInteractive()) {
            $this->line('Install completed. Run "php artisan geoip:update-mmdb" when ready.');

            return self::SUCCESS;
        }

        if (! $this->confirm('Download the MMDB now?', true)) {
            $this->line('Install completed. Run "php artisan geoip:update-mmdb" when ready.');

            return self::SUCCESS;
        }

        $exitCode = $this->call('geoip:update-mmdb');

        return $exitCode === self::SUCCESS ? self::SUCCESS : self::FAILURE;
    }
}
