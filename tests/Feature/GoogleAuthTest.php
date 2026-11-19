<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Support\GoogleOAuth;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\SocialiteManager;
use Laravel\Socialite\Two\GoogleProvider;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use \Tests\Support\ConfirmaCadastro;

    private MockHandler $http;
    private array $requests = [];

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'services.google.enabled' => true, 'services.google.client_id' => 'test-client',
            'services.google.client_secret' => 'test-secret',
            'services.google.redirect' => 'http://localhost/auth/google/callback',
        ]);
        DB::purge('sqlite');
        Schema::create('usuarios', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('email')->unique();
            $table->string('documento')->unique();
            $table->string('senha');
            $table->integer('tipo_usuario');
            $table->date('data_nasc');
            foreach (['telefone', 'cep', 'cidade', 'bairro', 'endereco', 'remember_token'] as $column) $table->string($column)->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id(); $table->morphs('tokenable'); $table->string('name');
            $table->string('token', 64)->unique(); $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable(); $table->timestamp('expires_at')->nullable(); $table->timestamps();
        });
        Schema::create('notificacoes', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('usuario_id'); $table->boolean('lida')->default(false);
        });
        (require database_path('migrations/2026_11_18_000000_add_privacy_and_terms_to_usuarios.php'))->up();
        (require database_path('migrations/2026_11_18_000002_add_google_id_to_usuarios.php'))->up();

        $this->http = new MockHandler();
        $stack = HandlerStack::create($this->http);
        $stack->push(Middleware::history($this->requests));
        $client = new Client(['handler' => $stack]);
        Socialite::swap(new SocialiteManager($this->app));
        Socialite::extend('google', fn ($app) => (new GoogleProvider(
            $app['request'], 'test-client', 'test-secret', config('services.google.redirect')
        ))->setHttpClient($client));
    }

    private function user(int $type = 3, ?string $googleId = null): Usuario
    {
        $n = Usuario::withTrashed()->count() + 1;
        $user = new Usuario([
            'nome' => 'Pessoa ' . $n, 'email' => 'pessoa' . $n . '@example.test',
            'documento' => '1234567890' . $n, 'senha' => Hash::make('senha-teste'),
            'data_nasc' => '1990-01-01', 'tipo_usuario' => $type,
        ]);
        $user->google_id = $googleId;
        $user->save();
        return $user;
    }

    private function begin(array $data = []): string
    {
        Socialite::forgetDrivers();
        $response = $this->post(route('google.redirect'), array_merge(['mode' => 'register', 'perfil_cadastro' => 'solicitante'], $data));
        $response->assertRedirect();
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
        return $query['state'];
    }

    private function identity(array $overrides = []): void
    {
        $this->http->append(new Response(200, [], json_encode(['access_token' => 'opaque-test-token', 'expires_in' => 3600])));
        $this->http->append(new Response(200, [], json_encode(array_merge([
            'sub' => 'google-123', 'email' => 'google@example.test', 'email_verified' => true,
            'name' => 'Pessoa Google', 'picture' => 'https://example.test/avatar.png',
        ], $overrides))));
    }

    private function returnFromGoogle(string $state, array $query = [])
    {
        Socialite::forgetDrivers();
        return $this->get(route('google.callback', array_merge(['state' => $state, 'code' => 'code-for-test'], $query)));
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'perfil_cadastro' => 'solicitante', 'nome' => 'Nome editado', 'email' => 'forged@example.test',
            'google_id' => 'forged', 'tipo_usuario' => 1, 'documento' => '98765432100',
            'data_nasc' => '1995-03-10', 'aceite_termos' => 1,
        ], $overrides);
    }

    public function test_redirect_uses_state_basic_scopes_and_exact_callback_without_secrets(): void
    {
        $state = $this->begin();
        $this->assertGreaterThanOrEqual(32, strlen($state));
        $this->assertSame($state, session('state'));
        $this->assertSame('solicitante', session(GoogleOAuth::CONTEXT . '.profile'));
        Socialite::forgetDrivers();
        $response = $this->post(route('google.redirect'), ['mode' => 'login']);
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->assertSame('openid profile email', $query['scope']);
        $this->assertSame(config('services.google.redirect'), $query['redirect_uri']);
        $this->assertSame('code', $query['response_type']);
        $this->assertSame('select_account', $query['prompt']);
        $this->assertArrayNotHasKey('client_secret', $query);
        $this->assertArrayNotHasKey('access_type', $query);
    }

    public function test_real_socialite_exchange_gets_verified_identity_then_requires_completion(): void
    {
        $state = $this->begin();
        $this->identity();
        $this->returnFromGoogle($state)->assertRedirect(route('google.complete'));
        $this->assertGuest();
        $this->assertDatabaseCount('usuarios', 0);
        $this->assertSame('google-123', session(GoogleOAuth::PENDING . '.id'));
        $this->assertSame('google@example.test', session(GoogleOAuth::PENDING . '.email'));
        $this->assertNull(session('state'));
        $this->assertNull(session(GoogleOAuth::CONTEXT));
        $this->assertStringNotContainsString('opaque-test-token', json_encode(session()->all()));
        $this->assertCount(2, $this->requests);
        $this->assertSame('/oauth2/v4/token', $this->requests[0]['request']->getUri()->getPath());
        $this->assertSame('Bearer opaque-test-token', $this->requests[1]['request']->getHeaderLine('Authorization'));
        $this->get(route('google.complete'))->assertOk()->assertSee('Google conectado:')->assertSee('google@example.test')
            ->assertDontSee('name="senha"', false)->assertSee('readonly', false);
    }

    public function test_new_clients_complete_with_trusted_identity_terms_and_private_location(): void
    {
        $state = $this->begin(); $this->identity(); $this->returnFromGoogle($state);
        $this->cadastrarEConfirmar(route('google.store'), $this->payload(['latitude' => -20, 'longitude' => -40]))->assertRedirect(route('perfil'));
        $user = Usuario::sole();
        $this->assertSame(3, $user->tipo_usuario);
        $this->assertSame('google@example.test', $user->email);
        $this->assertSame('google-123', $user->google_id);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull($user->termos_aceitos_em);
        $this->assertFalse($user->compartilhar_localizacao);
        $this->assertNull($user->latitude);
        $this->assertNull($user->longitude);
        $this->assertFalse(Hash::check('', $user->senha));
        $this->assertArrayNotHasKey('google_id', $user->toArray());
        $this->assertAuthenticatedAs($user);
        $this->assertNull(session(GoogleOAuth::PENDING));
    }

    public function test_artist_signup_preserves_budget_origin_and_optional_location_consent(): void
    {
        $origin = $this->user(2);
        $state = $this->begin(['perfil_cadastro' => 'artista', 'orcamento_artista' => $origin->id]);
        $this->identity(); $this->returnFromGoogle($state);
        $this->cadastrarEConfirmar(route('google.store'), $this->payload([
            'perfil_cadastro' => 'artista', 'orcamento_artista' => 'https://evil.test',
            'compartilhar_localizacao' => 1, 'latitude' => -20, 'longitude' => -40,
        ]))->assertRedirect(route('usuarios.perfilPublico', ['id' => $origin->id]));
        $user = Usuario::where('google_id', 'google-123')->sole();
        $this->assertSame(2, $user->tipo_usuario);
        $this->assertTrue($user->compartilhar_localizacao);
        $this->assertNotNull($user->localizacao_autorizada_em);
    }

    public function test_completion_requires_explicit_terms_and_existing_required_fields(): void
    {
        $state = $this->begin(); $this->identity(); $this->returnFromGoogle($state);
        $this->from(route('google.complete'))->post(route('google.store'), $this->payload([
            'aceite_termos' => null, 'documento' => '', 'data_nasc' => '',
        ]))->assertSessionHasErrors(['aceite_termos', 'documento', 'data_nasc'])->assertRedirect(route('google.complete'));
        $this->assertDatabaseCount('usuarios', 0);
        $this->assertNotNull(session(GoogleOAuth::PENDING));
    }

    public function test_forged_admin_profiles_are_rejected_on_start_and_completion(): void
    {
        $this->post(route('google.redirect'), ['mode' => 'register', 'perfil_cadastro' => 'admin'])->assertSessionHasErrors('perfil_cadastro');
        $state = $this->begin(); $this->identity(); $this->returnFromGoogle($state);
        $this->post(route('google.store'), $this->payload(['perfil_cadastro' => 'admin']))->assertSessionHasErrors('perfil_cadastro');
        $this->assertDatabaseCount('usuarios', 0);
    }

    public function test_linked_artist_and_client_can_login_without_changing_local_profile(): void
    {
        foreach ([2, 3] as $type) {
            $user = $this->user($type, 'google-' . $type);
            $state = $this->begin(['mode' => 'login']);
            $this->identity(['sub' => $user->google_id, 'email' => 'changed@example.test']);
            $this->returnFromGoogle($state)->assertRedirect(route('perfil'));
            $this->assertAuthenticatedAs($user);
            $this->assertSame($user->email, $user->fresh()->email);
            $this->assertSame($user->nome, $user->fresh()->nome);
            Auth::logout();
        }
    }

    public function test_deleted_and_admin_linked_accounts_cannot_use_google(): void
    {
        $admin = $this->user(1, 'google-admin');
        $deleted = $this->user(2, 'google-deleted'); $deleted->delete();
        foreach ([$admin, $deleted] as $user) {
            $state = $this->begin(['mode' => 'login']); $this->identity(['sub' => $user->google_id]);
            $this->returnFromGoogle($state)->assertRedirect(route('login'))->assertSessionHasErrors('google');
            $this->assertGuest();
        }
    }

    public function test_existing_email_is_never_automatically_linked_or_logged_in(): void
    {
        $user = $this->user();
        $state = $this->begin(['mode' => 'login']); $this->identity(['email' => strtoupper($user->email)]);
        $this->returnFromGoogle($state)->assertRedirect(route('login'))->assertSessionHasErrors('google');
        $this->assertGuest();
        $this->assertNull($user->fresh()->google_id);
        $this->assertNull(session(GoogleOAuth::PENDING));
    }

    public function test_email_must_be_verified_by_google(): void
    {
        foreach ([false, null, 'true', 1] as $verified) {
            $state = $this->begin(); $this->identity(['email_verified' => $verified]);
            $this->returnFromGoogle($state)->assertSessionHasErrors('google');
            $this->assertNull(session(GoogleOAuth::PENDING));
        }
        $this->assertGuest();
    }

    public function test_invalid_missing_expired_or_replayed_state_never_reaches_google(): void
    {
        $state = $this->begin();
        $this->returnFromGoogle('wrong')->assertSessionHasErrors('google');
        $this->returnFromGoogle($state)->assertSessionHasErrors('google');
        $state = $this->begin();
        $this->travel(11)->minutes();
        $this->returnFromGoogle($state)->assertSessionHasErrors('google');
        $this->assertCount(0, $this->requests);
        $this->assertGuest();
    }

    public function test_cancelled_and_failed_provider_requests_are_recoverable(): void
    {
        $state = $this->begin();
        $this->returnFromGoogle($state, ['error' => 'access_denied'])->assertSessionHasErrors('google');
        $this->assertCount(0, $this->requests);
        $state = $this->begin();
        $this->http->append(new Response(400, [], '{"error":"invalid_grant"}'));
        $this->returnFromGoogle($state)->assertSessionHasErrors('google');
        $this->assertNull(session(GoogleOAuth::CONTEXT));
        $this->assertNull(session('state'));
    }

    public function test_successful_callback_cannot_be_replayed(): void
    {
        $state = $this->begin(); $this->identity(); $this->returnFromGoogle($state)->assertRedirect(route('google.complete'));
        $this->returnFromGoogle($state)->assertSessionHasErrors('google');
        $this->assertCount(2, $this->requests);
        $this->assertDatabaseCount('usuarios', 0);
    }

    public function test_pending_signup_expires_and_post_without_identity_is_rejected(): void
    {
        $this->get(route('google.complete'))->assertRedirect(route('login'));
        $this->post(route('google.store'), $this->payload())->assertRedirect(route('login'));
        $state = $this->begin(); $this->identity(); $this->returnFromGoogle($state);
        $this->travel(11)->minutes();
        $this->post(route('google.store'), $this->payload())->assertRedirect(route('login'));
        $this->assertDatabaseCount('usuarios', 0);
    }

    public function test_account_created_during_completion_cannot_be_taken_over(): void
    {
        $state = $this->begin(); $this->identity(); $this->returnFromGoogle($state);
        $user = $this->user(); $user->email = 'google@example.test'; $user->save();
        $this->post(route('google.store'), $this->payload())->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertNull($user->fresh()->google_id);
    }

    public function test_authenticated_client_can_explicitly_link_then_login_with_google(): void
    {
        $user = $this->user();
        $this->actingAs($user)->post(route('google.link'))->assertRedirect();
        $state = session('state'); $this->identity(['email' => $user->email]);
        $this->returnFromGoogle($state)->assertRedirect(route('homepage'))->assertSessionHas('success');
        $this->assertSame('google-123', $user->fresh()->google_id);
        Auth::logout();
        $state = $this->begin(['mode' => 'login']); $this->identity(['email' => $user->email]);
        $this->returnFromGoogle($state)->assertRedirect(route('perfil'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_link_requires_authentication_and_rejects_admin(): void
    {
        $this->post(route('google.link'))->assertRedirect(route('login'));
        $this->actingAs($this->user(1))->post(route('google.link'))->assertForbidden();
    }

    public function test_link_rejects_different_email_already_linked_identity_and_changed_session(): void
    {
        $user = $this->user(); $other = $this->user(2, 'other-google');
        foreach ([['email' => $other->email], ['email' => $user->email, 'sub' => 'other-google']] as $identity) {
            Socialite::forgetDrivers();
            $this->actingAs($user)->post(route('google.link'));
            $state = session('state'); $this->identity($identity);
            $this->returnFromGoogle($state)->assertSessionHasErrors('google');
            $this->assertNull($user->fresh()->google_id);
        }
        Socialite::forgetDrivers();
        $this->actingAs($user)->post(route('google.link')); $state = session('state');
        $this->actingAs($other);
        $this->returnFromGoogle($state)->assertSessionHasErrors('google');
        $this->assertCount(4, $this->requests);
    }

    public function test_disabled_configuration_never_redirects_to_google_and_internal_login_stays_unchanged(): void
    {
        config(['services.google.client_secret' => null]);
        $this->post(route('google.redirect'), ['mode' => 'login'])->assertRedirect(route('login'))->assertSessionHasErrors('google');
        $this->get('/login')->assertOk()->assertSee('disabled title="Acesso com Google ainda não configurado"', false);
        $this->get('/login_interno')->assertOk()->assertDontSee('google-auth-button')->assertDontSee('navbar');
        $this->assertCount(0, $this->requests);
    }

    public function test_forms_have_csrf_google_brand_asset_and_no_nested_forms(): void
    {
        foreach (['/login', '/cadastro'] as $url) {
            $html = $this->get($url)->assertOk()->assertSee('imgs/google-g.png', false)->getContent();
            $dom = new \DOMDocument(); @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
            $xpath = new \DOMXPath($dom);
            $this->assertSame(0, $xpath->query('//form//form')->length);
            $id = $url === '/login' ? 'googleLoginForm' : 'googleRegistrationForm';
            $this->assertSame(1, $xpath->query('//form[@id="' . $id . '"]/input[@name="_token"]')->length);
            $this->assertSame(1, $xpath->query('//button[@form="' . $id . '" and @type="submit"]')->length);
        }
        $this->actingAs($this->user())->get('/home')->assertOk()->assertSee('form="googleLinkForm"', false);
    }

    public function test_oauth_posts_really_enforce_csrf(): void
    {
        $this->app['env'] = 'local';
        $this->post(route('google.redirect'), ['mode' => 'login'])->assertStatus(419);
        $this->post(route('google.store'), $this->payload())->assertStatus(419);
        $this->actingAs($this->user())->post(route('google.link'))->assertStatus(419);
        $this->assertCount(0, $this->requests);
    }

    public function test_no_code_is_rejected_without_exchange_and_callback_is_not_cached(): void
    {
        $state = $this->begin();
        Socialite::forgetDrivers();
        $response = $this->get(route('google.callback', ['state' => $state]));
        $response->assertSessionHasErrors('google')->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertCount(0, $this->requests);
    }

    public function test_budget_origin_is_revalidated_and_arbitrary_urls_are_never_redirected_to(): void
    {
        $origin = $this->user(2);
        $state = $this->begin(['orcamento_artista' => $origin->id]);
        $this->identity(); $this->returnFromGoogle($state); $origin->delete();
        $this->cadastrarEConfirmar(route('google.store'), $this->payload(['orcamento_artista' => 'https://evil.test']))->assertRedirect(route('perfil'));
    }

    public function test_unique_google_identity_is_not_mass_assignable(): void
    {
        $user = $this->user(3, 'unique-google');
        $user->fill(['google_id' => 'injected']); $user->save();
        $this->assertSame('unique-google', $user->fresh()->google_id);
        $this->assertDatabaseCount('usuarios', 1);
        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->user(2, 'unique-google');
    }
}
