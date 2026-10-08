<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Providers\RouteServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class AuthFormsDesignTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('usuarios', function (Blueprint $table) {
            $table->id();
            foreach (['nome', 'documento', 'email', 'senha', 'telefone', 'cep', 'cidade', 'bairro', 'endereco', 'remember_token'] as $column) {
                $table->string($column)->nullable();
            }
            $table->integer('tipo_usuario');
            $table->date('data_nasc')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    private function xpath(string $html): \DOMXPath
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        return new \DOMXPath($dom);
    }

    public function test_interactive_background_is_shared_only_by_auth_pages(): void
    {
        foreach (['/login', '/cadastro', '/cadastro?tipo=solicitante'] as $route) {
            $response = $this->get($route)->assertOk()->assertSee('js/auth-pixel-background.js', false);
            $xpath = $this->xpath($response->getContent());
            $this->assertSame(1, $xpath->query('//canvas[@data-auth-pixels and @aria-hidden="true"]')->length);
            $this->assertSame(0, $xpath->query('//form//canvas[@data-auth-pixels]')->length);
            $this->assertSame(1, $xpath->query('//script[contains(@src, "auth-pixel-background.js") and @defer]')->length);
        }
        $this->get('/home')->assertOk()->assertDontSee('data-auth-pixels', false);
    }

    public function test_login_card_preserves_fields_and_native_registration_links(): void
    {
        $response = $this->get('/login')->assertOk()->assertSee('css/auth-forms.css', false);
        $xpath = $this->xpath($response->getContent());
        $this->assertSame(1, $xpath->query('//form/input[@name="_token"]')->length);
        foreach (['email', 'password'] as $name) {
            $input = $xpath->query('//form//input[@name="' . $name . '"]')->item(0);
            $this->assertNotNull($input);
            $this->assertSame(1, $xpath->query('//label[@for="' . $input->getAttribute('id') . '"]')->length);
        }
        $this->assertSame(2, $xpath->query('//div[@class="auth-signup-actions"]/a')->length);
        $this->assertSame(0, $xpath->query('//a/button')->length);
    }

    public function test_unified_registration_preserves_all_fields_labels_map_and_masks(): void
    {
        foreach (['/cadastro', '/cadastro?tipo=solicitante'] as $route) {
            $response = $this->get($route)->assertOk()->assertSee('css/auth-forms.css', false)
                ->assertSee('data-address-form', false)->assertSee('data-address-map', false)
                ->assertSee('js/cadastro.js', false)->assertSee('css/lever-switch.css', false)
                ->assertSee('js/vendor/imask.min.js', false)->assertDontSee('https://unpkg.com/imask', false);
            $xpath = $this->xpath($response->getContent());
            foreach (['nome', 'email', 'telefone', 'documento', 'data_nasc', 'cep', 'cidade', 'bairro', 'endereco', 'senha', 'senha_confirmation'] as $name) {
                $inputs = $xpath->query('//form[@id="form-cadastro"]//input[@name="' . $name . '"]');
                $this->assertSame(1, $inputs->length);
                $this->assertSame(1, $xpath->query('//label[@for="' . $inputs->item(0)->getAttribute('id') . '"]')->length);
            }
            $this->assertSame(0, $xpath->query('//input[@name="sexo_usuario"]')->length);
            $this->assertSame(1, $xpath->query('//form[@id="form-cadastro"]//input[@name="latitude"]')->length);
            $this->assertSame(1, $xpath->query('//form[@id="form-cadastro"]//input[@name="longitude"]')->length);
            $this->assertSame(1, $xpath->query('//form[@id="form-cadastro"]/button[@type="submit"]')->length);
        }
    }

    public function test_registration_validation_preserves_input_and_escapes_values(): void
    {
        $this->withSession(['errors' => (new ViewErrorBag())->put('default', new MessageBag(['documento' => 'Informe o CPF ou CNPJ.'])), '_old_input' => [
            'nome' => '<script>alert(1)</script>',
            'email' => 'pessoa@example.test', 'latitude' => '-25.4284', 'longitude' => '-49.2733',
        ]]);
        $response = $this->get('/cadastro')->assertOk()
            ->assertSee('Informe o CPF ou CNPJ.')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
        $xpath = $this->xpath($response->getContent());
        $this->assertSame('pessoa@example.test', $xpath->query('//input[@id="cadastro-email"]')->item(0)->getAttribute('value'));
        $this->assertSame(0, $xpath->query('//input[@name="sexo_usuario"]')->length);
        $this->assertSame('-25.4284', $xpath->query('//input[@name="latitude"]')->item(0)->getAttribute('value'));
    }

    public function test_login_validation_preserves_email_and_does_not_echo_password(): void
    {
        $this->withSession([
            'errors' => (new ViewErrorBag())->put('default', new MessageBag(['password' => 'Senha incorreta.'])),
            '_old_input' => ['email' => 'pessoa@example.test'],
        ]);
        $response = $this->get('/login')->assertOk()->assertSee('Senha incorreta.');
        $xpath = $this->xpath($response->getContent());
        $this->assertSame('pessoa@example.test', $xpath->query('//input[@id="email"]')->item(0)->getAttribute('value'));
        $this->assertFalse($xpath->query('//input[@id="password"]')->item(0)->hasAttribute('value'));
    }

    public function test_existing_login_still_authenticates_custom_usuario_model(): void
    {
        $user = Usuario::create([
            'nome' => 'Pessoa', 'email' => 'pessoa@example.test', 'tipo_usuario' => 3,
            'senha' => Hash::make('senha-segura'),
        ]);
        $this->post('/login', ['email' => $user->email, 'password' => 'senha-segura'])
            ->assertRedirect(RouteServiceProvider::HOME);
        $this->assertAuthenticatedAs($user);
    }

    private function registrationPayload(int $type = 3): array
    {
        return [
            'nome' => 'Pessoa', 'documento' => '12345678901', 'email' => 'pessoa@example.test',
            'data_nasc' => '1990-01-01', 'senha' => 'senha-segura',
            'senha_confirmation' => 'senha-segura', 'telefone' => '48999999999',
            'cep' => '88801001', 'cidade' => 'Criciuma', 'bairro' => 'Centro',
            'endereco' => 'Rua de teste', 'latitude' => -28.6775, 'longitude' => -49.3697,
            'perfil_cadastro' => $type === 2 ? 'artista' : 'solicitante', 'tipo_usuario' => 1,
        ];
    }

    private function register(string $route, int $type): void
    {
        $this->post($route, $this->registrationPayload($type))->assertSessionHasNoErrors()->assertRedirect(route('perfil'));
        $user = Usuario::firstOrFail();
        $this->assertSame($type, (int) $user->tipo_usuario);
        $this->assertSame('-28.67750000', $user->latitude);
        $this->assertTrue(Hash::check('senha-segura', $user->senha));
        $this->assertAuthenticatedAs($user);
    }

    public function test_artist_registration_still_saves_expected_fields(): void
    {
        $this->register(route('usuarios.storeArtista'), 2);
    }

    public function test_requester_registration_still_saves_expected_fields(): void
    {
        $this->register(route('usuarios.storeContratante'), 3);
    }

    public function test_unified_artist_registration_ignores_injected_admin_type(): void
    {
        $this->register(route('usuarios.cadastrar'), 2);
    }

    public function test_unified_requester_registration_ignores_injected_admin_type(): void
    {
        $this->register(route('usuarios.cadastrar'), 3);
    }

    public function test_budget_registration_preserves_origin_and_returns_authenticated_to_the_artist(): void
    {
        $artist = Usuario::create(['nome' => 'Artista de origem', 'tipo_usuario' => 2]);
        $url = route('usuarios.cadastro', ['tipo' => 'solicitante', 'orcamento_artista' => $artist->id]);
        $response = $this->get($url)->assertOk()->assertSee('Cadastro de solicitante');
        $xpath = $this->xpath($response->getContent());
        $this->assertSame((string) $artist->id, $xpath->query('//form[@id="form-cadastro"]//input[@name="orcamento_artista" and @type="hidden"]')->item(0)->getAttribute('value'));
        $this->post(route('usuarios.cadastrar'), $this->registrationPayload() + ['orcamento_artista' => $artist->id])
            ->assertSessionHasNoErrors()->assertSessionHas('success')
            ->assertRedirect(route('usuarios.perfilPublico', ['id' => $artist->id]));
        $requester = Usuario::where('email', 'pessoa@example.test')->firstOrFail();
        $this->assertSame(3, (int) $requester->tipo_usuario);
        $this->assertAuthenticatedAs($requester);
        $this->assertArrayNotHasKey('orcamento_artista', $requester->getAttributes());
    }

    public function test_budget_origin_survives_validation_errors_and_profile_switching(): void
    {
        $artist = Usuario::create(['nome' => 'Artista de origem', 'tipo_usuario' => 2]);
        $this->from(route('usuarios.cadastro'))->post(route('usuarios.cadastrar'), [
            'perfil_cadastro' => 'solicitante', 'nome' => 'Pessoa', 'orcamento_artista' => $artist->id,
        ])->assertSessionHasErrors(['documento', 'senha']);
        $response = $this->get(route('usuarios.cadastro'))->assertOk()->assertSee('Cadastro de solicitante');
        $this->assertSame((string) $artist->id, $this->xpath($response->getContent())->query('//input[@name="orcamento_artista"]')->item(0)->getAttribute('value'));
        $this->assertGuest();
        $this->post(route('usuarios.cadastrar'), $this->registrationPayload(2) + ['orcamento_artista' => $artist->id])
            ->assertSessionHasNoErrors()->assertRedirect(route('usuarios.perfilPublico', ['id' => $artist->id]));
        $this->assertSame(2, (int) Usuario::where('email', 'pessoa@example.test')->firstOrFail()->tipo_usuario);
    }

    public function test_a_separate_regular_registration_does_not_reuse_previous_budget_origin(): void
    {
        $artist = Usuario::create(['nome' => 'Artista de origem', 'tipo_usuario' => 2]);
        $this->get(route('usuarios.cadastro', ['orcamento_artista' => $artist->id]))->assertOk()->assertSee('name="orcamento_artista"', false);
        $this->get(route('usuarios.cadastro'))->assertOk()->assertDontSee('name="orcamento_artista"', false);
        $this->post(route('usuarios.cadastrar'), $this->registrationPayload())->assertSessionHasNoErrors()->assertRedirect(route('perfil'));
    }

    public function test_invalid_missing_non_artist_and_deleted_origins_cannot_redirect_registration(): void
    {
        $requester = Usuario::create(['nome' => 'Outro solicitante', 'tipo_usuario' => 3]);
        $deleted = Usuario::create(['nome' => 'Artista removido', 'tipo_usuario' => 2]);
        $deleted->delete();
        foreach (['https://example.test/externo', '//example.test', ['id' => 1], '0', '-1', '99999', $requester->id, $deleted->id] as $index => $origin) {
            $this->get(route('usuarios.cadastro', ['orcamento_artista' => $origin]))->assertOk()->assertDontSee('name="orcamento_artista"', false);
            $payload = $this->registrationPayload();
            $payload['email'] = 'pessoa' . $index . '@example.test';
            $payload['documento'] = '1234567890' . $index;
            $this->post(route('usuarios.cadastrar'), $payload + ['orcamento_artista' => $origin])
                ->assertSessionHasNoErrors()->assertRedirect(route('perfil'));
            $this->assertAuthenticated();
            \Illuminate\Support\Facades\Auth::logout();
        }
    }

    public function test_budget_registration_falls_back_if_artist_disappears_before_submission(): void
    {
        $artist = Usuario::create(['nome' => 'Artista de origem', 'tipo_usuario' => 2]);
        $this->get(route('usuarios.cadastro', ['orcamento_artista' => $artist->id]))->assertOk();
        $artist->delete();
        $this->post(route('usuarios.cadastrar'), $this->registrationPayload() + ['orcamento_artista' => $artist->id])
            ->assertSessionHasNoErrors()->assertRedirect(route('perfil'));
        $this->assertAuthenticated();
    }

    public function test_legacy_registration_redirects_preserve_budget_origin(): void
    {
        $artist = Usuario::create(['nome' => 'Artista de origem', 'tipo_usuario' => 2]);
        foreach (['/cadastro/artista' => 'artista', '/cadastro/contratante' => 'solicitante'] as $path => $profile) {
            $this->get($path . '?orcamento_artista=' . $artist->id)
                ->assertRedirect(route('usuarios.cadastro', ['tipo' => $profile, 'orcamento_artista' => $artist->id]));
        }
    }

    public function test_unified_registration_rejects_missing_invalid_and_admin_profiles(): void
    {
        foreach ([null, '', 'admin', '1', '2', '3', ['artista']] as $value) {
            $this->postJson(route('usuarios.cadastrar'), ['perfil_cadastro' => $value, 'tipo_usuario' => 1])
                ->assertUnprocessable()->assertJsonValidationErrors('perfil_cadastro');
        }
        $this->assertDatabaseCount('usuarios', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertGuest();
    }

    public function test_validation_keeps_selected_profile_and_values_in_the_same_form(): void
    {
        $this->from(route('usuarios.cadastro'))->post(route('usuarios.cadastrar'), [
            'perfil_cadastro' => 'solicitante', 'nome' => 'Pessoa de teste', 'email' => 'teste@example.test',
        ])->assertRedirect(route('usuarios.cadastro'))->assertSessionHasErrors(['documento', 'data_nasc', 'senha']);
        $response = $this->get(route('usuarios.cadastro'))->assertOk()->assertSee('Cadastro de solicitante');
        $xpath = $this->xpath($response->getContent());
        $this->assertSame(1, $xpath->query('//select[@name="perfil_cadastro"]/option[@value="solicitante" and @selected]')->length);
        $this->assertSame(1, $xpath->query('//input[@id="cadastro-profile-switch" and @checked]')->length);
        $this->assertSame('Pessoa de teste', $xpath->query('//input[@name="nome"]')->item(0)->getAttribute('value'));
        $this->assertDatabaseCount('usuarios', 0);
    }

    public function test_legacy_registration_urls_redirect_to_the_unified_form_with_correct_preset(): void
    {
        $this->get('/cadastro/artista')->assertRedirect(route('usuarios.cadastro', ['tipo' => 'artista']));
        $this->get('/cadastro/contratante')->assertRedirect(route('usuarios.cadastro', ['tipo' => 'solicitante']));
        foreach (['artista', 'solicitante'] as $profile) {
            $response = $this->get(route('usuarios.cadastro', ['tipo' => $profile]))->assertOk();
            $xpath = $this->xpath($response->getContent());
            $this->assertSame(1, $xpath->query('//select[@name="perfil_cadastro"]/option[@value="' . $profile . '" and @selected]')->length);
            $this->assertSame(1, $xpath->query('//form[@id="form-cadastro"]')->length);
            $this->assertSame(route('usuarios.cadastrar'), $xpath->query('//form[@id="form-cadastro"]')->item(0)->getAttribute('action'));
        }
    }

    public function test_registration_links_open_unified_page_without_selection_modal(): void
    {
        foreach (['/home', '/login', '/cadastro'] as $url) {
            $response = $this->get($url)->assertOk()->assertDontSee('cadastroModal', false);
            $xpath = $this->xpath($response->getContent());
            $this->assertSame(1, $xpath->query('//nav//a[@href="' . route('usuarios.cadastro') . '"]')->length);
            $this->assertSame(0, $xpath->query('//nav//button[contains(text(), "Cadastrar-se")]')->length);
        }
    }

    public function test_legacy_validation_flash_survives_redirect_to_unified_page(): void
    {
        $this->from('/cadastro/contratante')->post('/cadastro/contratante', ['nome' => 'Pessoa de teste'])
            ->assertSessionHasErrors('documento');
        $this->get('/cadastro/contratante')->assertRedirect(route('usuarios.cadastro', ['tipo' => 'solicitante']));
        $response = $this->get(route('usuarios.cadastro', ['tipo' => 'solicitante']))
            ->assertOk()->assertSee('Informe o CPF ou CNPJ.');
        $xpath = $this->xpath($response->getContent());
        $this->assertSame('Pessoa de teste', $xpath->query('//input[@name="nome"]')->item(0)->getAttribute('value'));
    }

    public function test_profile_can_be_updated_without_gender_and_ignores_legacy_field(): void
    {
        $user = Usuario::create([
            'nome' => 'Pessoa', 'email' => 'pessoa@example.test', 'tipo_usuario' => 3,
            'senha' => Hash::make('senha-segura'),
        ]);
        $this->actingAs($user)->put(route('usuarios.update', $user->id), [
            'nome' => 'Pessoa atualizada', 'sexo_usuario' => 2,
            'cep' => '88801-001', 'latitude' => -28.6775, 'longitude' => -49.3697,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('Pessoa atualizada', $user->fresh()->nome);
        $this->assertSame('-28.67750000', $user->fresh()->latitude);
        $this->assertArrayNotHasKey('sexo_usuario', $user->fresh()->getAttributes());
        Schema::create('notificacoes', function (Blueprint $table) {
            $table->id();
            $table->integer('usuario_id');
            $table->boolean('lida')->default(false);
        });
        $this->assertStringNotContainsString('name="sexo_usuario"', view('Components.navbarbootstrap')->render());
    }

    public function test_migration_removes_legacy_column_and_keeps_existing_users(): void
    {
        Schema::create('sexo_usuario', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
        });
        Schema::table('usuarios', fn (Blueprint $table) => $table->unsignedBigInteger('sexo_usuario')->nullable());
        DB::table('sexo_usuario')->insert(['id' => 1, 'nome' => 'Masculino']);
        $id = DB::table('usuarios')->insertGetId(['nome' => 'Pessoa', 'tipo_usuario' => 2, 'sexo_usuario' => 1]);
        $migration = require database_path('migrations/2026_10_07_000001_remove_sexo_usuario.php');
        $migration->up();
        $this->assertFalse(Schema::hasColumn('usuarios', 'sexo_usuario'));
        $this->assertFalse(Schema::hasTable('sexo_usuario'));
        $this->assertDatabaseHas('usuarios', ['id' => $id, 'nome' => 'Pessoa']);
        $migration->down();
        $this->assertTrue(Schema::hasColumn('usuarios', 'sexo_usuario'));
        $this->assertDatabaseHas('usuarios', ['id' => $id, 'sexo_usuario' => null]);
        $this->assertSame(3, DB::table('sexo_usuario')->count());
    }
}
