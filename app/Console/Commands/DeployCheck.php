<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DeployCheck extends Command
{
    protected $signature = 'deploy:check {--startup : Check only settings required to start the web container} {--connections : Also check database connectivity and required tables}';
    protected $description = 'Validate production configuration without displaying secrets or changing data';

    public function handle(): int
    {
        if (!config('deployment.strict')) {
            if (getenv('VERCEL')) {
                $this->error('DEPLOYMENT_STRICT cannot be disabled on Vercel.');
                return self::FAILURE;
            }
            $this->warn('Non-strict local test mode. This is not a production readiness result.');
            return self::SUCCESS;
        }

        $key = config('app.key');
        $decoded = is_string($key) && str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;
        try {
            $db = (new \Illuminate\Database\ConfigurationUrlParser())->parseConfiguration(config('database.connections.pgsql'));
        } catch (\Throwable $exception) {
            $this->error('Invalid database URL. Review the private environment settings.');
            return self::FAILURE;
        }
        $checks = [
            'APP_ENV=production' => app()->isProduction(),
            'APP_DEBUG=false' => !config('app.debug'),
            'APP_KEY is a 32-byte production secret' => is_string($decoded) && strlen($decoded) === 32,
            'APP_URL uses HTTPS and is not localhost' => parse_url(config('app.url'), PHP_URL_SCHEME) === 'https'
                && !in_array(parse_url(config('app.url'), PHP_URL_HOST), [null, 'localhost', '127.0.0.1'], true),
            'HTTPS URL generation enabled' => (bool) config('deployment.force_https'),
            'Trusted reverse proxies configured' => filled(config('deployment.trusted_proxies')),
            'Secure, encrypted, host-only session cookies' => config('session.secure') && config('session.encrypt') && !config('session.domain'),
            'Database-backed sessions and cache' => config('session.driver') === 'database' && config('cache.default') === 'database',
            'PostgreSQL configured' => config('database.default') === 'pgsql' && ($db['driver'] ?? null) === 'pgsql',
            'PostgreSQL identity and password configured' => filled($db['host'] ?? null)
                && filled($db['username'] ?? null) && filled($db['password'] ?? null),
            'Runtime identity is not the default database administrator' => !str_starts_with((string) ($db['username'] ?? ''), 'postgres'),
            'PostgreSQL TLS verifies the server certificate' => ($db['sslmode'] ?? '') === 'verify-full'
                && is_readable((string) ($db['sslrootcert'] ?? '')),
            'Transaction pooler prepared-statement compatibility' => (int) ($db['port'] ?? 5432) !== 6543
                || ($db['options'][\PDO::ATTR_EMULATE_PREPARES] ?? false),
            'Public files use persistent object storage' => config('filesystems.disks.public.driver') === 's3',
            'Private files use persistent object storage' => config('filesystems.disks.private.driver') === 's3',
            'Public and private files use different buckets' => filled(config('filesystems.disks.private.bucket'))
                && config('filesystems.disks.private.bucket') !== config('filesystems.disks.public.bucket'),
            'Logs go to stderr' => config('logging.default') === 'stderr',
            'Google is disabled or fully configured' => !config('services.google.enabled') || \App\Support\GoogleOAuth::configured(),
        ];

        foreach (['public', 'private'] as $disk) {
            $storage = config('filesystems.disks.'.$disk, []);
            $checks['Object storage '.$disk.' credentials configured'] = filled($storage['key'] ?? null)
                && filled($storage['secret'] ?? null) && filled($storage['region'] ?? null) && filled($storage['bucket'] ?? null);
            $checks['Object storage '.$disk.' uses HTTPS'] = str_starts_with($storage['endpoint'] ?? '', 'https://');
        }
        $checks['Public media URL uses HTTPS'] = str_starts_with(config('filesystems.disks.public.url') ?? '', 'https://');

        if (!$this->option('startup')) {
            $checks['Resend SMTP configured with TLS'] = config('mail.default') === 'smtp'
                && config('mail.mailers.smtp.host') === 'smtp.resend.com'
                && in_array(config('mail.mailers.smtp.encryption'), ['tls', 'ssl'], true)
                && filled(config('mail.mailers.smtp.password'));
            $checks['Sentry configured without personal data'] = filled(config('sentry.dsn')) && !config('sentry.send_default_pii');
        }
        if ($this->option('connections')) {
            try {
                if (!$checks['PostgreSQL configured'] || !$checks['PostgreSQL TLS verifies the server certificate']) {
                    throw new \RuntimeException('Connection checks require verified PostgreSQL TLS.');
                }
                DB::connection()->select('select 1');
                $checks['Database reachable'] = true;
                $role = DB::selectOne("select (rolsuper or rolcreatedb or rolcreaterole or rolbypassrls or has_schema_privilege(current_user, 'public', 'CREATE'))::int as elevated from pg_roles where rolname = current_user");
                $checks['Database runtime role has no elevated or schema creation privileges'] = $role && (int) $role->elevated === 0;
                foreach (['migrations', 'sessions', 'cache', 'cache_locks', 'usuarios'] as $table) {
                    $checks['Required table: '.$table] = Schema::hasTable($table);
                }
                $visual = app(\App\Support\VisualSistema::class);
                $checks['Terms administrator and privacy contact configured'] = filled($visual->termosResponsavel()) && filled($visual->termosContatoEmail());
                foreach (\App\Support\StorageDeploymentCheck::issues() as $issue) $checks[$issue] = false;
            } catch (\Throwable $exception) {
                $checks['Database reachable and schema available'] = false;
            }
        }
        foreach ($checks as $label => $ok) $this->line(($ok ? '[OK] ' : '[PENDING] ').$label);
        $this->newLine();
        $ok = !in_array(false, array_map(fn ($value) => (bool) $value, $checks), true);
        $this->line($ok ? 'Checks passed. External services still require end-to-end validation.' : 'Deployment is not ready. Resolve the pending settings before publishing.');
        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
