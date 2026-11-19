<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustHosts as Middleware;

class TrustHosts extends Middleware
{
    /**
     * Get the host patterns that should be trusted.
     *
     * @return array<int, string|null>
     */
    public function hosts(): array
    {
        $hosts = array_merge(config('deployment.trusted_hosts', []), [
            parse_url(config('app.url'), PHP_URL_HOST), config('deployment.vercel_host'),
        ]);

        return array_map(fn ($host) => '^'.preg_quote($host, '/').'$', array_values(array_unique(array_filter($hosts))));
    }
}
