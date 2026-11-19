<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProductionAccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
    }

    public function test_internal_user_directory_is_not_public_or_available_to_regular_users(): void
    {
        foreach (['/usuarios', '/usuarios/create', '/usuarios/1/edit'] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
        $client = $this->user();
        foreach (['/usuarios', '/usuarios/create', '/usuarios/'.$client->id.'/edit'] as $url) {
            $this->actingAs($client)->get($url)->assertForbidden();
        }
    }

    public function test_notifications_require_login_and_cannot_mark_someone_elses_records(): void
    {
        $this->postJson('/notificacoes/ler-todas')->assertUnauthorized();
        $a = $this->user(); $b = $this->user('other@example.test');
        $portfolio = DB::table('portfolio_artistas')->insertGetId(['id_usuario' => $b->id]);
        $proposal = DB::table('proposta_contrato')->insertGetId(['id_artista' => $portfolio, 'id_usuario_avaliador' => $a->id]);
        $id = DB::table('notificacoes')->insertGetId(['usuario_id' => $b->id, 'proposta_id' => $proposal, 'mensagem' => 'Private', 'lida' => false]);
        $this->actingAs($a)->postJson('/notificacoes/'.$id.'/marcar-lida')->assertNotFound();
        $this->postJson('/notificacoes/ler-todas')->assertOk();
        $this->assertFalse((bool) DB::table('notificacoes')->where('id', $id)->value('lida'));
    }

    public function test_serialized_users_do_not_expose_documents_birthdays_or_detailed_address(): void
    {
        $data = $this->user()->toArray();
        foreach (['senha', 'documento', 'data_nasc', 'cep', 'endereco', 'bairro'] as $key) $this->assertArrayNotHasKey($key, $data);
    }

    public function test_pdf_larger_than_the_platform_response_limit_returns_validation(): void
    {
        config(['uploads.pdf_max_bytes' => 10]);
        $artist = $this->user(); $artist->tipo_usuario = 2; $artist->save();
        $portfolio = \App\Models\PortfolioArtista::create(['id_usuario' => $artist->id]);
        $exporter = \Mockery::mock(\App\Support\PortfolioPdf::class);
        $exporter->shouldReceive('generate')->once()->andReturn(str_repeat('x', 11));
        $this->app->instance(\App\Support\PortfolioPdf::class, $exporter);
        $this->actingAs($artist)->postJson(route('portfolio.exportar-pdf', $portfolio), ['campos' => ['nome']])
            ->assertUnprocessable()->assertJsonValidationErrors('exportacao');
    }

    public function test_admin_bootstrap_is_explicit_interactive_and_has_no_default_password(): void
    {
        $this->artisan('app:create-admin')
            ->expectsQuestion('Nome', 'Admin Teste')
            ->expectsQuestion('E-mail', 'admin@example.test')
            ->expectsQuestion('CPF ou CNPJ (oculto)', '11122233344')
            ->expectsQuestion('Nascimento (AAAA-MM-DD)', '1990-01-01')
            ->expectsQuestion('Senha (minimo 12 caracteres)', 'test-only-password-123')
            ->expectsQuestion('Confirme a senha', 'test-only-password-123')
            ->assertSuccessful();
        $admin = Usuario::where('email', 'admin@example.test')->sole();
        $this->assertSame(1, (int) $admin->tipo_usuario);
        $this->assertTrue(Hash::check('test-only-password-123', $admin->senha));
        $this->assertFalse($admin->compartilhar_localizacao);
    }

    private function user(string $email = 'client@example.test'): Usuario
    {
        return Usuario::create(['nome' => 'Teste', 'email' => $email, 'documento' => $email,
            'data_nasc' => '1990-01-01', 'senha' => Hash::make('test-password'), 'tipo_usuario' => 3]);
    }
}
