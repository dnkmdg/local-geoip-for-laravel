# dnkmdg/local-geoip-for-laravel

Local IP geolocation for Laravel using MaxMind MMDB.

## Features

- Local MMDB lookups via `geoip2/geoip2`
- No runtime external HTTP calls during request handling
- Swappable abstraction via `Dnkmdg\LocalGeoIp\Contracts\GeoIpLookup`
- Normalized DTO: `Dnkmdg\LocalGeoIp\Data\GeoIpLocationData`
- Trusted-proxy-aware request IP candidate resolver
- Built-in MMDB update command: `geoip:update-mmdb`

## Install

```bash
composer require dnkmdg/local-geoip-for-laravel geoip2/geoip2
```

## Publish config

```bash
php artisan vendor:publish --tag=string-ip-lookup-config
```

## Guided install (with first download prompt)

```bash
php artisan geoip:install
```

This command publishes config and, in interactive mode, prompts whether to run the first MMDB download immediately.

## Config keys

- `database_path` (default: `storage/app/geoip/GeoLite2-City.mmdb`)
- `cache_ttl` (seconds, default: `86400`)
- `database_max_age_days` (default: `45`)
- `trusted_proxies` (array from env CSV)
- `forwarded_headers` (default: `CF-Connecting-IP,True-Client-IP,X-Forwarded-For,X-Real-IP`)
- `override_secret` (optional)
- `update.enabled`
- `update.account_id`
- `update.license_key`
- `update.edition_id` (default: `GeoLite2-City`)
- `update.download_url` (default: `https://download.maxmind.com/app/geoip_download`)

## Usage

```php
use Dnkmdg\LocalGeoIp\Contracts\GeoIpLookup;
use Dnkmdg\LocalGeoIp\RequestIpResolver;

$candidates = app(RequestIpResolver::class)->resolveCandidates($request);
$geoIp = app(GeoIpLookup::class);

$location = null;
foreach ($candidates as $candidate) {
    $location = $geoIp->lookup($candidate);
    if ($location?->countryCode !== null) {
        break;
    }
}
```

## Update MMDB

```bash
php artisan geoip:update-mmdb
```

## Scheduler integration (in consuming app)

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('geoip:update-mmdb')->weeklyOn(1, '03:15');
```

Ensure system cron runs `php artisan schedule:run` every minute.

## Publishing

1. Push the repository to GitHub.
2. Add this package to Packagist: `dnkmdg/local-geoip-for-laravel`.
3. In Packagist, enable auto-update via the GitHub hook.
4. Create a semantic version tag and push it:

```bash
git tag v0.1.0
git push origin v0.1.0
```

`Release` workflow creates the GitHub release for pushed tags matching `v*.*.*`.
