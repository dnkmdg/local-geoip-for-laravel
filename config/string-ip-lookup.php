<?php

declare(strict_types=1);

$trustedProxyCsv = (string) env('STRING_IP_LOOKUP_TRUSTED_PROXIES', '');
$trustedProxies = array_values(array_filter(array_map('trim', explode(',', $trustedProxyCsv))));

$headersCsv = (string) env('STRING_IP_LOOKUP_FORWARDED_HEADERS', 'CF-Connecting-IP,True-Client-IP,X-Forwarded-For,X-Real-IP');
$forwardedHeaders = array_values(array_filter(array_map('trim', explode(',', $headersCsv))));

return [
    'database_path' => env('STRING_IP_LOOKUP_DATABASE_PATH', storage_path('app/geoip/GeoLite2-City.mmdb')),
    'cache_ttl' => (int) env('STRING_IP_LOOKUP_CACHE_TTL', 86400),
    'database_max_age_days' => (int) env('STRING_IP_LOOKUP_DATABASE_MAX_AGE_DAYS', 45),
    'trusted_proxies' => $trustedProxies,
    'forwarded_headers' => $forwardedHeaders,
    'override_secret' => env('STRING_IP_LOOKUP_OVERRIDE_SECRET'),
    'update' => [
        'enabled' => (bool) env('STRING_IP_LOOKUP_UPDATE_ENABLED', true),
        'account_id' => env('MAXMIND_ACCOUNT_ID'),
        'license_key' => env('MAXMIND_LICENSE_KEY'),
        'edition_id' => env('STRING_IP_LOOKUP_EDITION_ID', 'GeoLite2-City'),
        'download_url' => env('STRING_IP_LOOKUP_DOWNLOAD_URL', 'https://download.maxmind.com/app/geoip_download'),
    ],
];
