<?php

return [
    'force_https' => (bool) env('APP_FORCE_HTTPS', false),
    'trusted_proxies' => env('TRUSTED_PROXIES'),
    'trusted_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('APP_TRUSTED_HOSTS', ''))))),
    'vercel_host' => env('VERCEL_URL'),
    'strict' => (bool) env('DEPLOYMENT_STRICT', true),
];
