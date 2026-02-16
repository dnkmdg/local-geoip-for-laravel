<?php

declare(strict_types=1);

$trustedProxyCsv = (string) env('LOCAL_GEOIP_TRUSTED_PROXIES', '');
$trustedProxies = array_values(array_filter(array_map('trim', explode(',', $trustedProxyCsv))));

$headersCsv = (string) env('LOCAL_GEOIP_FORWARDED_HEADERS', 'CF-Connecting-IP,True-Client-IP,X-Forwarded-For,X-Real-IP');
$forwardedHeaders = array_values(array_filter(array_map('trim', explode(',', $headersCsv))));

return [
    'database_path' => env('LOCAL_GEOIP_DATABASE_PATH', storage_path('app/geoip/GeoLite2-Country.mmdb')),
    'cache_ttl' => (int) env('LOCAL_GEOIP_CACHE_TTL', 86400),
    'database_max_age_days' => (int) env('LOCAL_GEOIP_DATABASE_MAX_AGE_DAYS', 45),
    'trusted_proxies' => $trustedProxies,
    'forwarded_headers' => $forwardedHeaders,
    'override_secret' => env('LOCAL_GEOIP_OVERRIDE_SECRET'),
    'update' => [
        'enabled' => (bool) env('LOCAL_GEOIP_UPDATE_ENABLED', true),
        'account_id' => env('MAXMIND_ACCOUNT_ID'),
        'license_key' => env('MAXMIND_LICENSE_KEY'),
        'edition_id' => env('LOCAL_GEOIP_UPDATE_EDITION_ID', env('LOCAL_GEOIP_EDITION_ID', 'GeoLite2-Country')),
        'download_url' => env(
            'LOCAL_GEOIP_UPDATE_DOWNLOAD_URL',
            env('LOCAL_GEOIP_DOWNLOAD_URL', 'https://download.maxmind.com/geoip/databases/{edition_id}/download')
        ),
    ],
];
