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

Prefer contract-first tests in consuming apps:
- Test app behavior against `Dnkmdg\LocalGeoIp\Contracts\GeoIpLookup`.
- Keep one adapter/integration test with real MMDB wiring; keep business tests contract-driven.

Use package testing helpers in consumer tests:
- `Dnkmdg\LocalGeoIp\Testing\InteractsWithLocalGeoIp`
- `fakeGeoIp([...])` to bind deterministic lookup responses.
- `fakeRequestIps([...])` to bypass proxy/header parsing and force candidate order.

Fixture strategy:
- Use deterministic local MMDB fixtures (country DB is usually enough).
- Avoid live MaxMind downloads in tests.

Failure-mode checklist for consuming apps:
- Missing database file.
- Invalid/private/reserved IP path.
- Lookup returns `null` path.
- Updater credential failure (`401`) and permission/network failure (`403`/connection).

Scheduler tests in consuming apps:
- Assert `geoip:update-mmdb` is scheduled with your expected cadence/timezone.
- Gate scheduled execution with `config('local-geoip.update.enabled')` and assert both enabled/disabled paths.

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
