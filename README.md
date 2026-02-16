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

Create a MaxMind GeoLite account and license key before first database download:
- Signup: [https://www.maxmind.com/en/geolite2/signup](https://www.maxmind.com/en/geolite2/signup)
- License keys: [https://www.maxmind.com/en/accounts/current/license-key](https://www.maxmind.com/en/accounts/current/license-key)
- Update docs: [https://dev.maxmind.com/geoip/updating-databases/](https://dev.maxmind.com/geoip/updating-databases/)

## Guided install (with first download prompt)

```bash
php artisan geoip:install
```

This command publishes config and, in interactive mode, prompts whether to run the first MMDB download immediately.

## Config keys

- `database_path` (default: `storage/app/geoip/GeoLite2-Country.mmdb`)
- `cache_ttl` (seconds, default: `86400`)
- `database_max_age_days` (default: `45`)
- `trusted_proxies` (array from env CSV)
- `forwarded_headers` (default: `CF-Connecting-IP,True-Client-IP,X-Forwarded-For,X-Real-IP`)
- `override_secret` (optional)
- `update.enabled` (default: `true`; scheduler policy toggle)
- `update.account_id` (`MAXMIND_ACCOUNT_ID`)
- `update.license_key` (`MAXMIND_LICENSE_KEY`)
- `update.edition_id` (`LOCAL_GEOIP_UPDATE_EDITION_ID`, default: `GeoLite2-Country`)
- `update.download_url` (`LOCAL_GEOIP_UPDATE_DOWNLOAD_URL`, default: `https://download.maxmind.com/geoip/databases/{edition_id}/download`)

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

Facade usage:

```php
use Dnkmdg\LocalGeoIp\Facades\GeoIpLookup;

$location = GeoIpLookup::resolve('8.8.8.8');
```

## Consumer testing

Example: fake contract responses in a feature test

```php
<?php

use Dnkmdg\LocalGeoIp\Data\GeoIpLocationData;
use Dnkmdg\LocalGeoIp\Testing\InteractsWithLocalGeoIp;

uses(InteractsWithLocalGeoIp::class);

it('applies market from geoip country', function () {
    $this->fakeGeoIp([
        '8.8.8.8' => new GeoIpLocationData(
            ipAddress: '8.8.8.8',
            countryCode: 'SE',
            countryName: 'Sweden',
            city: null,
            regionCode: null,
            regionName: null,
            latitude: null,
            longitude: null,
            timeZone: null,
            postalCode: null,
        ),
    ]);

    $response = $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])
        ->get('/market-aware-endpoint');

    $response->assertOk();
    $response->assertSessionHas('market', 'se');
});
```

Example: force request candidate IP order

```php
<?php

use Dnkmdg\LocalGeoIp\Testing\InteractsWithLocalGeoIp;

uses(InteractsWithLocalGeoIp::class);

it('uses first candidate ip from resolver fake', function () {
    $this->fakeRequestIps(['203.0.113.11', '203.0.113.12']);

    $response = $this->get('/market-aware-endpoint');

    $response->assertOk();
    $response->assertSessionHas('resolved_ip', '203.0.113.11');
});
```

Example: scheduler gating by config

```php
<?php

use Illuminate\Support\Facades\Schedule;

it('schedules geoip update only when enabled', function () {
    config()->set('local-geoip.update.enabled', true);
    app(\App\Console\Kernel::class)->schedule(Schedule::getFacadeRoot());
    expect(collect(Schedule::events())->pluck('command')->implode(' '))
        ->toContain('geoip:update-mmdb');
});
```

## Update MMDB

```bash
php artisan geoip:update-mmdb
```

If you get `HTTP 401`, verify `MAXMIND_ACCOUNT_ID` + `MAXMIND_LICENSE_KEY`, confirm the key can download GeoLite databases, then run `php artisan config:clear`.
If you get `HTTP 403` or connection errors, ensure your proxy/firewall allows HTTPS redirects to `mm-prod-geoip-databases.a2649acb697e2c09b632799562c076f2.r2.cloudflarestorage.com`.

## Scheduler integration (in consuming app)

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('geoip:update-mmdb')
    ->weeklyOn(1, '03:15')
    ->when(fn () => (bool) config('local-geoip.update.enabled'));
```

Ensure system cron runs `php artisan schedule:run` every minute.
