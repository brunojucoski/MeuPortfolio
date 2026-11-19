<?php

namespace Tests\Feature;

use App\Support\TelemetryPrivacy;
use Sentry\Breadcrumb;
use Sentry\Event;
use Sentry\ExceptionDataBag;
use Sentry\Frame;
use Sentry\Stacktrace;
use Sentry\UserDataBag;
use Tests\TestCase;

class ProductionConfigurationTest extends TestCase
{
    public function test_health_is_stateless_and_does_not_need_a_database(): void
    {
        $this->withoutExceptionHandling();
        config(['database.default' => 'missing', 'session.driver' => 'database']);
        $this->get('/health')->assertOk()->assertExactJson(['status' => 'ok'])
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame([], $this->get('/health')->headers->getCookies());
    }

    public function test_dynamic_pages_are_not_cached_and_oauth_keeps_its_stricter_referrer_policy(): void
    {
        $this->get('/login')->assertOk()->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response = $this->get('/auth/google/callback');
        $response->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_password_recovery_pages_use_the_existing_brand_and_portuguese_forms(): void
    {
        $this->get('/forgot-password')->assertOk()->assertSee('Recuperar senha')->assertSee('css/auth-forms.css', false);
        $this->get('/reset-password/qa-token?email=qa@example.test')->assertOk()
            ->assertSee('Redefinir senha')->assertSee('name="password_confirmation"', false);
    }

    public function test_missing_production_settings_fail_without_exposing_values(): void
    {
        config(['deployment.strict' => true, 'app.key' => 'do-not-print-this-key', 'database.connections.pgsql.password' => 'do-not-print-this-password']);
        $this->artisan('deploy:check', ['--startup' => true])
            ->expectsOutputToContain('[PENDING]')
            ->doesntExpectOutputToContain('do-not-print-this-key')
            ->doesntExpectOutputToContain('do-not-print-this-password')
            ->assertFailed();
    }

    public function test_production_configuration_check_passes_for_explicit_secure_settings(): void
    {
        $this->app['env'] = 'production';
        config([
            'app.env' => 'production', 'app.debug' => false, 'app.key' => 'base64:'.base64_encode(str_repeat('x', 32)),
            'app.url' => 'https://meuportfolio.ink', 'deployment.force_https' => true,
            'deployment.strict' => true, 'deployment.trusted_proxies' => '*',
            'session.secure' => true, 'session.encrypt' => true, 'session.domain' => null,
            'session.driver' => 'database', 'cache.default' => 'database', 'database.default' => 'pgsql',
            'database.connections.pgsql.host' => 'example.pooler.supabase.com',
            'database.connections.pgsql.username' => 'app_runtime.project',
            'database.connections.pgsql.password' => 'test-not-a-real-secret',
            'database.connections.pgsql.sslmode' => 'verify-full',
            'database.connections.pgsql.sslrootcert' => __FILE__,
            'database.connections.pgsql.options' => [\PDO::ATTR_EMULATE_PREPARES => true],
            'filesystems.disks.public.driver' => 's3', 'logging.default' => 'stderr',
            'filesystems.disks.public.key' => 'test', 'filesystems.disks.public.secret' => 'test',
            'filesystems.disks.public.region' => 'test-region', 'filesystems.disks.public.bucket' => 'public',
            'filesystems.disks.public.endpoint' => 'https://storage.example.test',
            'filesystems.disks.public.url' => 'https://storage.example.test/public',
            'filesystems.disks.private.driver' => 's3', 'filesystems.disks.private.key' => 'test',
            'filesystems.disks.private.secret' => 'test', 'filesystems.disks.private.region' => 'test-region',
            'filesystems.disks.private.bucket' => 'private', 'filesystems.disks.private.endpoint' => 'https://storage.example.test',
            'services.google.enabled' => false,
        ]);
        $this->artisan('deploy:check', ['--startup' => true])->assertSuccessful();
        config(['database.connections.pgsql.url' => 'mysql://app_runtime:qa-only@example.test/database']);
        $this->artisan('deploy:check', ['--startup' => true])->assertFailed();
        config(['database.connections.pgsql.url' => 'postgresql://app_runtime:qa-only@example.test:6543/postgres?sslmode=disable']);
        $this->artisan('deploy:check', ['--startup' => true])->assertFailed();
    }

    public function test_sentry_whitelist_removes_request_user_sql_messages_and_local_variables(): void
    {
        $secret = 'private-cpf-password-cookie';
        $frame = new Frame('store', 'UsuarioController.php', 120, null, null, ['senha' => $secret]);
        $frame->setContextLine($secret)->setPreContext([$secret])->setPostContext([$secret]);
        $event = Event::createEvent();
        $event->setMessage($secret)->setExtra(['password' => $secret])->setContext('private', ['cpf' => $secret]);
        $event->setRequest(['url' => 'https://example.test/?token='.$secret, 'data' => ['senha' => $secret], 'headers' => ['Cookie' => $secret]]);
        $event->setUser(UserDataBag::createFromArray(['email' => $secret.'@example.test']));
        $event->setTag('documento', $secret);
        $event->setExceptions([new ExceptionDataBag(new \RuntimeException($secret), new Stacktrace([$frame]))]);
        $clean = TelemetryPrivacy::beforeSend($event);
        $this->assertSame($event->getId(), $clean->getId());
        $this->assertSame([], $clean->getRequest());
        $this->assertNull($clean->getUser());
        $this->assertSame([], $clean->getExtra());
        $this->assertSame([], $clean->getContexts());
        $this->assertSame([], $clean->getTags());
        $this->assertSame([], $clean->getBreadcrumbs());
        $this->assertStringNotContainsString($secret, $clean->getMessage());
        $exception = $clean->getExceptions()[0];
        $this->assertSame(\RuntimeException::class, $exception->getType());
        $this->assertStringNotContainsString($secret, $exception->getValue());
        $cleanFrame = $exception->getStacktrace()->getFrames()[0];
        $this->assertSame([], $cleanFrame->getVars());
        $this->assertNull($cleanFrame->getContextLine());
        $this->assertSame([], $cleanFrame->getPreContext());
        $this->assertSame([], $cleanFrame->getPostContext());
        $this->assertNull(TelemetryPrivacy::dropTransaction(Event::createTransaction()));
    }

    public function test_container_excludes_credentials_and_never_migrates_or_regenerates_keys_on_start(): void
    {
        $ignore = file_get_contents(base_path('.dockerignore'));
        foreach (['.env', '.env.*', '.git', 'storage', 'public/storage'] as $entry) $this->assertStringContainsString($entry, $ignore);
        $entrypoint = file_get_contents(base_path('docker/entrypoint.sh'));
        $this->assertStringNotContainsString('artisan migrate', $entrypoint);
        $this->assertStringNotContainsString('key:generate', $entrypoint);
        $this->assertStringContainsString('deploy:check --startup', $entrypoint);
        $this->assertStringContainsString('USER www-data', file_get_contents(base_path('Dockerfile')));
    }

    public function test_vercel_environment_explicitly_matches_the_container_port(): void
    {
        $example = file_get_contents(base_path('.env.production.example'));
        $dockerfile = file_get_contents(base_path('Dockerfile'));
        $this->assertMatchesRegularExpression('/^PORT=8080\s*$/m', $example);
        $this->assertStringContainsString('PORT=8080', $dockerfile);
        $this->assertStringContainsString('{$PORT:8080}', file_get_contents(base_path('docker/Caddyfile')));
    }

    public function test_stderr_logs_drop_exception_messages_and_private_context(): void
    {
        $record = new \Monolog\LogRecord(new \DateTimeImmutable(), 'test', \Monolog\Level::Error,
            'password=private-value', ['exception' => new \RuntimeException('CPF private-value'), 'senha' => 'private-value']);
        $safe = (new \App\Support\PrivacyLogProcessor())($record);
        $this->assertStringNotContainsString('private-value', $safe->message.json_encode($safe->context));
        $this->assertSame(\RuntimeException::class, $safe->context['exception_type']);
        $this->assertSame([], $safe->extra);
    }
}
