<?php

namespace Tests\Feature;

use App\Models\ConfiguracaoSistema;
use App\Models\PortfolioArtista;
use App\Models\Usuario;
use App\Support\VisualSistema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ConfiguracaoSistemaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('usuarios', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('email');
            $table->string('senha')->nullable();
            $table->string('remember_token')->nullable();
            $table->integer('tipo_usuario');
            $table->timestamps();
            $table->softDeletes();
        });
        (require database_path('migrations/2026_10_07_000000_create_configuracoes_sistema.php'))->up();
        (require database_path('migrations/2026_10_07_000002_add_logo_to_configuracoes_sistema.php'))->up();
        Storage::fake('public');
    }

    private function usuario(int $tipo = 1): Usuario
    {
        return Usuario::create(['nome' => 'Admin de teste', 'email' => 'admin' . $tipo . '@example.test', 'tipo_usuario' => $tipo, 'senha' => Hash::make('senha-de-teste')]);
    }

    private function dados(): array
    {
        return ['cor_base' => '#287850', 'sobre_titulo' => 'Nossa comunidade', 'sobre_texto' => "Artistas e pessoas.\nUma nova linha."];
    }

    public function test_migration_preserves_original_defaults(): void
    {
        $this->assertDatabaseCount('configuracoes_sistema', 1);
        $visual = app(VisualSistema::class);
        $this->assertSame('#6d2e2e', $visual->corBase());
        $this->assertSame('#8f4444', $visual->corSecundaria());
        $this->assertStringContainsString('sacredheart.png', $visual->imagemUrl('login'));
        $this->assertSame(config('visual-sistema.sobre_texto'), $visual->sobreTexto());
        $this->assertNull($visual->imagemUrl('logo'));
    }

    public function test_guest_cannot_view_or_update_settings(): void
    {
        $this->get(route('admin.configuracoes.edit'))->assertRedirect(route('loginInterno.form'));
        $this->put(route('admin.configuracoes.update'), $this->dados())->assertRedirect(route('loginInterno.form'));
        $this->assertDatabaseHas('configuracoes_sistema', ['id' => 1, 'cor_base' => '#6d2e2e']);
    }

    public function test_artists_and_requesters_cannot_view_or_update_settings(): void
    {
        foreach ([2, 3] as $tipo) {
            $this->actingAs($this->usuario($tipo));
            $this->get(route('admin.configuracoes.edit'))->assertForbidden();
            $this->put(route('admin.configuracoes.update'), $this->dados() + ['imagem_logo' => UploadedFile::fake()->image('logo.png')])->assertForbidden();
        }
        $this->assertSame([], Storage::disk('public')->allFiles('sistema/imagens'));
        $this->assertDatabaseHas('configuracoes_sistema', ['cor_base' => '#6d2e2e']);
    }

    public function test_admin_can_view_and_save_configuration(): void
    {
        $admin = $this->usuario();
        $this->actingAs($admin)->get(route('admin.configuracoes.edit'))->assertOk()
            ->assertSee('Configurações visuais')->assertSee('multipart/form-data', false)
            ->assertSee('data-image-preview', false)->assertSee('name="sobre_texto"', false)
            ->assertSee('name="imagem_logo"', false)->assertSee('Usar nome padrão');
        $this->put(route('admin.configuracoes.update'), $this->dados())->assertRedirect(route('admin.configuracoes.edit'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('configuracoes_sistema', $this->dados() + ['atualizado_por' => $admin->id]);
        $this->assertDatabaseCount('configuracoes_sistema', 1);
    }

    public function test_uploaded_images_are_applied_to_all_corresponding_public_pages(): void
    {
        $this->actingAs($this->usuario());
        $uploads = [];
        foreach (array_keys(config('visual-sistema.imagens')) as $local) {
            $uploads['imagem_' . $local] = UploadedFile::fake()->image($local . '.png', 80, 80);
        }
        $this->put(route('admin.configuracoes.update'), $this->dados() + $uploads)->assertSessionHasNoErrors();
        $configuracao = ConfiguracaoSistema::findOrFail(1);
        Auth::logout();
        foreach (['login' => '/login', 'cadastro_artista' => '/cadastro?tipo=artista', 'cadastro_solicitante' => '/cadastro?tipo=solicitante', 'sobre' => '/sobre'] as $local => $url) {
            $caminho = $configuracao->{'imagem_' . $local};
            Storage::disk('public')->assertExists($caminho);
            $this->get($url)->assertOk()->assertSee('storage/' . $caminho, false)->assertSee('--sistema-cor-base: #287850', false);
        }
        $this->get('/login_interno')->assertOk()->assertDontSee('storage/' . $configuracao->imagem_login, false);
    }

    public function test_logo_is_shared_by_public_navigation_offcanvas_and_admin(): void
    {
        $admin = $this->usuario();
        $this->actingAs($admin)->put(route('admin.configuracoes.update'), $this->dados() + [
            'imagem_logo' => UploadedFile::fake()->image('logo.png', 380, 92),
        ])->assertSessionHasNoErrors();
        $path = ConfiguracaoSistema::find(1)->imagem_logo;
        Storage::disk('public')->assertExists($path);
        $this->get(route('admin.configuracoes.edit'))->assertOk()->assertSee('storage/' . $path, false);
        Auth::logout();
        foreach (['/home', '/login', '/cadastro', '/sobre'] as $url) {
            $html = $this->get($url)->assertOk()->assertSee('css/navbar-shell.css', false)->getContent();
            $dom = new \DOMDocument();
            @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
            $xpath = new \DOMXPath($dom);
            $this->assertSame(2, $xpath->query('//nav//a/img[@class="site-brand-image"]')->length);
            $this->assertSame(0, $xpath->query('//nav//span[@class="site-brand-text"]')->length);
            foreach ($xpath->query('//nav//a/img[@class="site-brand-image"]') as $image) {
                $this->assertSame('MeuPortfólio', $image->getAttribute('alt'));
                $this->assertStringEndsWith('/storage/' . $path, $image->getAttribute('src'));
            }
        }
        $this->get('/login_interno')->assertOk()->assertDontSee('<nav', false)->assertDontSee('storage/' . $path, false);
    }

    public function test_replacing_and_restoring_logo_keeps_other_images_and_configuration(): void
    {
        $this->actingAs($this->usuario());
        $url = route('admin.configuracoes.update');
        $this->put($url, $this->dados() + [
            'imagem_logo' => UploadedFile::fake()->image('primeiro.png'),
            'imagem_login' => UploadedFile::fake()->image('login.png'),
        ])->assertSessionHasNoErrors();
        $primeiro = ConfiguracaoSistema::find(1)->imagem_logo;
        $login = ConfiguracaoSistema::find(1)->imagem_login;
        $this->put($url, $this->dados() + ['imagem_logo' => UploadedFile::fake()->image('segundo.webp')])->assertSessionHasNoErrors();
        $segundo = ConfiguracaoSistema::find(1)->imagem_logo;
        Storage::disk('public')->assertMissing($primeiro);
        Storage::disk('public')->assertExists($segundo);
        $this->put($url, $this->dados() + ['restaurar_logo' => 1])->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing($segundo);
        Storage::disk('public')->assertExists($login);
        $this->assertDatabaseHas('configuracoes_sistema', $this->dados() + ['imagem_logo' => null, 'imagem_login' => $login]);
        Auth::logout();
        $this->get('/home')->assertOk()->assertSee('<span class="site-brand-text">MeuPortfólio</span>', false)->assertDontSee('site-brand-image', false);
    }

    public function test_logo_upload_rejects_unsafe_or_oversized_files_and_keeps_previous_logo(): void
    {
        $this->actingAs($this->usuario());
        $url = route('admin.configuracoes.update');
        $this->put($url, $this->dados() + ['imagem_logo' => UploadedFile::fake()->image('valido.png')])->assertSessionHasNoErrors();
        $original = ConfiguracaoSistema::find(1)->imagem_logo;
        foreach ([
            UploadedFile::fake()->create('script.svg', 1, 'image/svg+xml'),
            UploadedFile::fake()->image('grande.png')->size(2049),
            UploadedFile::fake()->image('larga.png', 6001, 10),
        ] as $arquivo) {
            $this->put($url, $this->dados() + ['imagem_logo' => $arquivo])->assertSessionHasErrors('imagem_logo');
            $this->assertSame($original, ConfiguracaoSistema::find(1)->imagem_logo);
        }
        Storage::disk('public')->assertExists($original);
        $this->assertCount(1, Storage::disk('public')->allFiles('sistema/imagens'));
    }

    public function test_logo_migration_can_be_rolled_back_without_changing_existing_settings(): void
    {
        ConfiguracaoSistema::find(1)->update($this->dados());
        $migration = require database_path('migrations/2026_10_07_000002_add_logo_to_configuracoes_sistema.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('configuracoes_sistema', 'imagem_logo'));
        $this->assertDatabaseHas('configuracoes_sistema', $this->dados());
        $migration->up();
        $this->assertDatabaseHas('configuracoes_sistema', $this->dados() + ['imagem_logo' => null]);
    }

    public function test_invalid_colors_files_and_empty_text_are_rejected_without_side_effects(): void
    {
        $this->actingAs($this->usuario());
        $this->put(route('admin.configuracoes.update'), array_merge($this->dados(), [
            'cor_base' => 'red; color: blue', 'sobre_texto' => '',
            'imagem_login' => UploadedFile::fake()->create('script.svg', 1, 'image/svg+xml'),
            'imagem_sobre' => UploadedFile::fake()->image('grande.jpg')->size(2049),
            'imagem_cadastro_artista' => UploadedFile::fake()->image('larga.png', 6001, 10),
        ]))->assertSessionHasErrors(['cor_base', 'sobre_texto', 'imagem_login', 'imagem_sobre', 'imagem_cadastro_artista']);
        $this->assertSame([], Storage::disk('public')->allFiles('sistema/imagens'));
        $this->assertDatabaseHas('configuracoes_sistema', ['cor_base' => '#6d2e2e']);
    }

    public function test_replacing_and_restoring_images_removes_only_obsolete_uploads(): void
    {
        $this->actingAs($this->usuario());
        $url = route('admin.configuracoes.update');
        $this->put($url, $this->dados() + ['imagem_login' => UploadedFile::fake()->image('primeira.jpg')])->assertSessionHasNoErrors();
        $primeira = ConfiguracaoSistema::find(1)->imagem_login;
        $this->put($url, $this->dados() + ['imagem_login' => UploadedFile::fake()->image('segunda.png')])->assertSessionHasNoErrors();
        $segunda = ConfiguracaoSistema::find(1)->imagem_login;
        Storage::disk('public')->assertMissing($primeira);
        Storage::disk('public')->assertExists($segunda);
        $this->put($url, $this->dados() + ['restaurar_login' => 1])->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing($segunda);
        $this->assertNull(ConfiguracaoSistema::find(1)->imagem_login);
        $this->assertFileExists(public_path('imgs/sacredheart.png'));
    }

    public function test_database_failure_cleans_new_uploads_and_keeps_current_settings(): void
    {
        $this->actingAs($this->usuario());
        ConfiguracaoSistema::updating(fn () => throw new \RuntimeException('Falha simulada'));
        try {
            $this->put(route('admin.configuracoes.update'), $this->dados() + ['imagem_login' => UploadedFile::fake()->image('nova.png')])
                ->assertSessionHasErrors('configuracoes');
            $this->assertSame([], Storage::disk('public')->allFiles('sistema/imagens'));
            $this->assertDatabaseHas('configuracoes_sistema', ['cor_base' => '#6d2e2e']);
        } finally {
            ConfiguracaoSistema::flushEventListeners();
        }
    }

    public function test_about_content_is_rendered_as_safe_plain_text(): void
    {
        ConfiguracaoSistema::find(1)->update(['sobre_titulo' => '<script>alert(1)</script>', 'sobre_texto' => "Texto\n<img src=x onerror=alert(1)>"]);
        $this->get('/sobre')->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertSee('&lt;img src=x onerror=alert(1)&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_internal_login_shows_only_the_shared_form_and_allows_only_admins(): void
    {
        $this->get('/login_interno')->assertOk()->assertSee('css/auth-forms.css', false)
            ->assertSee('auth-internal-page', false)->assertSee('autocomplete="current-password"', false)
            ->assertDontSee('<nav', false)->assertDontSee('<footer', false)->assertDontSee('<img', false)
            ->assertDontSee('data-auth-pixels', false)->assertDontSee('<script', false)
            ->assertDontSee('Cadastrar-se')->assertDontSee('Buscar portfólios');
        foreach ([2, 3] as $tipo) {
            $user = $this->usuario($tipo);
            $this->from('/login_interno')->post('/login_interno', ['email' => $user->email, 'password' => 'senha-de-teste'])
                ->assertRedirect('/login_interno')->assertSessionHasErrors('email');
            $this->assertGuest();
        }
        $admin = $this->usuario();
        $this->post('/login_interno', ['email' => $admin->email, 'password' => 'senha-de-teste'])
            ->assertRedirect(route('categorias-artisticas.index'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_portfolio_colors_take_precedence_over_the_global_theme(): void
    {
        ConfiguracaoSistema::find(1)->update(['cor_base' => '#287850']);
        $user = $this->usuario(2);
        $portfolio = new PortfolioArtista(['cor_primaria_portfolio' => '#234567', 'cor_secundaria_portfolio' => '#abcdef']);
        $portfolio->id = 1;
        foreach (['categoriasOrcamento', 'posts', 'perguntasPropostaContrato'] as $relation) $portfolio->setRelation($relation, collect());
        $portfolio->setRelation('perguntasPropostaContrato', collect([new \App\Models\PerguntaPropostaContrato([
            'id' => 1, 'titulo' => 'Detalhes do trabalho', 'tipo' => 'texto',
        ])]));
        $user->setRelation('portfolioArtista', $portfolio)->setRelation('categoriasArtisticas', collect());
        $album = new \App\Models\CategoriaPostPortfolio(['nome' => 'Desenhos']);
        $album->id = 1;
        $this->view('usuarios.perfil_publico', [
            'usuario' => $user, 'feedbacksParaMedia' => collect([(object) ['nota' => 5], (object) ['nota' => 4]]),
            'feedbacksParaLista' => collect(), 'errors' => new \Illuminate\Support\ViewErrorBag(),
            'categoriasPortfolio' => collect([$album]),
        ])
            ->assertSee('<body style="--roxo-appolo: #234567; --roxo-claro: #abcdef;">', false)
            ->assertSee('--sistema-cor-base: #287850', false)
            ->assertSee('perfil-header-rating', false)->assertSee('<strong>4.5</strong>', false)
            ->assertSee('perfil-orcamento-actions', false)->assertSee('perfil-orcamento-button', false)
            ->assertSee('--orcamento-texto: #ffffff; --orcamento-texto-hover: #000000;', false)
            ->assertSee('<i class="bi bi-send" aria-hidden="true"></i><span>Enviar orçamento</span>', false)
            ->assertSee('data-bs-target="#modalConviteCadastroSolicitante"', false)
            ->assertSeeInOrder(['id="portfolio-posts"', 'id="portfolio-categorias"'], false)
            ->assertSee(e(route('usuarios.cadastro', ['tipo' => 'solicitante', 'orcamento_artista' => $user->id])), false);
    }
}
