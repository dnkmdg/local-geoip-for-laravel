<?php

declare(strict_types=1);

namespace Dnkmdg\LocalGeoIp;

use Dnkmdg\LocalGeoIp\Commands\InstallCommand;
use Illuminate\Support\ServiceProvider;
use Dnkmdg\LocalGeoIp\Commands\UpdateMmdbCommand;
use Dnkmdg\LocalGeoIp\Contracts\GeoIpLookup;

final class LocalGeoIpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/local-geoip.php', 'local-geoip');

        $this->app->singleton(GeoIpLookup::class, MmdbGeoIpLookup::class);

        $this->app->singleton(RequestIpResolver::class, function ($app): RequestIpResolver {
            $config = $app['config']->get('local-geoip', []);

            return new RequestIpResolver(
                trustedProxies: $config['trusted_proxies'] ?? [],
                forwardedHeaders: $config['forwarded_headers'] ?? [],
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/local-geoip.php' => config_path('local-geoip.php'),
        ], 'local-geoip-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                UpdateMmdbCommand::class,
            ]);
        }
    }
}
