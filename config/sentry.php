<?php

return [
    'dsn' => env('SENTRY_LARAVEL_DSN', env('SENTRY_DSN')),
    'environment' => env('APP_ENV', 'production'),
    'release' => env('SENTRY_RELEASE', env('VERCEL_GIT_COMMIT_SHA')),
    'send_default_pii' => false,
    'max_request_body_size' => 'never',
    'context_lines' => 0,
    'enable_logs' => false,
    'enable_metrics' => false,
    'traces_sample_rate' => 0.0,
    'profiles_sample_rate' => 0.0,
    'strict_trace_continuation' => true,
    'before_send' => [\App\Support\TelemetryPrivacy::class, 'beforeSend'],
    'before_send_transaction' => [\App\Support\TelemetryPrivacy::class, 'dropTransaction'],
    'breadcrumbs' => [
        'logs' => false, 'cache' => false, 'livewire' => false, 'sql_queries' => false,
        'sql_bindings' => false, 'queue_info' => false, 'command_info' => false,
        'http_client_requests' => false, 'notifications' => false,
    ],
    'tracing' => ['default_integrations' => false],
];
