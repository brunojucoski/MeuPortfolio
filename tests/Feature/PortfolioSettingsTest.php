<?php

namespace Tests\Feature;

use App\Models\CategoriaArtistica;
use App\Models\PortfolioArtista;
use App\Models\Usuario;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PortfolioSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('usuarios', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('email')->nullable();
            $table->integer('tipo_usuario');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('portfolio_artistas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_usuario')->constrained('usuarios');
            foreach (['nome_artistico', 'descricao', 'link_instagram', 'link_behance', 'cor_primaria_portfolio', 'cor_secundaria_portfolio'] as $column) {
                $table->string($column)->nullable();
            }
            $table->integer('estilo_card_categorias_portfolio')->default(1);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('categorias_artisticas', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->timestamps();
            $table->softDeletes();
        });
        (require database_path('migrations/2026_10_06_000003_add_social_links_to_portfolio_artistas.php'))->up();
        Schema::create('categorias_usuarios', function (Blueprint $table) {
            $table->foreignId('id_usuario')->constrained('usuarios');
            $table->foreignId('id_categoria')->constrained('categorias_artisticas');
        });
        Schema::create('notificacoes', function (Blueprint $table) {
            $table->id();
            $table->integer('usuario_id');
            $table->boolean('lida')->default(false);
        });
    }

    private function artista(): Usuario
    {
        return Usuario::create(['nome' => 'Artista', 'tipo_usuario' => 2, 'email' => 'artista@example.test']);
    }

    public function test_general_fields_colors_and_style_are_saved_together(): void
    {
        $usuario = $this->artista();
        $portfolio = PortfolioArtista::create(['id_usuario' => $usuario->id]);
        $categoria = CategoriaArtistica::create(['nome' => 'Pintura']);
        $this->actingAs($usuario)->put(route('portfolio.update', $portfolio), [
            'nome_artistico' => 'Atelie', 'descricao' => 'Pinturas',
            'link_instagram' => 'https://instagram.com/atelie', 'link_behance' => 'https://example.com',
            'cor_primaria_portfolio' => '#23774f', 'cor_secundaria_portfolio' => '#91bdac',
            'estilo_card_categorias_portfolio' => 5, 'categorias_form' => 1, 'categorias' => [$categoria->id],
        ])->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertDatabaseHas('portfolio_artistas', ['id' => $portfolio->id, 'nome_artistico' => 'Atelie', 'cor_primaria_portfolio' => '#23774f', 'cor_secundaria_portfolio' => '#91bdac', 'estilo_card_categorias_portfolio' => 5]);
        $this->assertDatabaseHas('categorias_usuarios', ['id_usuario' => $usuario->id, 'id_categoria' => $categoria->id]);
    }

    public function test_new_portfolio_accepts_style_and_colors(): void
    {
        $usuario = $this->artista();
        $this->actingAs($usuario)->post(route('portfolio.store'), [
            'nome_artistico' => 'Atelie', 'cor_primaria_portfolio' => '#23774f',
            'cor_secundaria_portfolio' => '#91bdac', 'estilo_card_categorias_portfolio' => 2, 'categorias_form' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('portfolio_artistas', ['id_usuario' => $usuario->id, 'estilo_card_categorias_portfolio' => 2, 'cor_primaria_portfolio' => '#23774f']);
    }

    public function test_invalid_colors_and_styles_do_not_change_portfolio(): void
    {
        $usuario = $this->artista();
        $portfolio = PortfolioArtista::create(['id_usuario' => $usuario->id, 'nome_artistico' => 'Original']);
        $this->actingAs($usuario)->put(route('portfolio.update', $portfolio), [
            'nome_artistico' => 'Alterado', 'cor_primaria_portfolio' => 'red', 'estilo_card_categorias_portfolio' => 99,
        ])->assertSessionHasErrors(['cor_primaria_portfolio', 'estilo_card_categorias_portfolio']);
        $this->assertSame('Original', $portfolio->fresh()->nome_artistico);
    }

    public function test_selected_areas_can_be_removed_or_cleared_without_changing_other_fields(): void
    {
        $user = $this->artista();
        $portfolio = PortfolioArtista::create(['id_usuario' => $user->id, 'nome_artistico' => 'Original']);
        $first = CategoriaArtistica::create(['nome' => 'Pintura']);
        $second = CategoriaArtistica::create(['nome' => 'Gravura']);
        $user->categoriasArtisticas()->sync([$first->id, $second->id]);
        $this->actingAs($user)->put(route('portfolio.update', $portfolio), ['categorias_form' => 1, 'categorias' => [$second->id]])->assertSessionHasNoErrors();
        $this->assertSame([$second->id], $user->categoriasArtisticas()->pluck('categorias_artisticas.id')->all());
        $this->put(route('portfolio.update', $portfolio), ['categorias_form' => 1])->assertSessionHasNoErrors();
        $this->assertSame(0, $user->categoriasArtisticas()->count());
        $this->assertSame('Original', $portfolio->fresh()->nome_artistico);
    }

    public function test_invalid_or_deleted_area_ids_do_not_partially_save_settings(): void
    {
        $user = $this->artista();
        $portfolio = PortfolioArtista::create(['id_usuario' => $user->id, 'nome_artistico' => 'Original']);
        $valid = CategoriaArtistica::create(['nome' => 'Pintura']);
        $removed = CategoriaArtistica::create(['nome' => 'Removida']);
        $removed->delete();
        $user->categoriasArtisticas()->sync([$valid->id]);
        $this->actingAs($user);
        foreach ([[$valid->id, 999], [$removed->id], [$valid->id, $valid->id], [['id' => $valid->id]]] as $ids) {
            $this->put(route('portfolio.update', $portfolio), ['nome_artistico' => 'Alterado', 'categorias_form' => 1, 'categorias' => $ids])->assertSessionHasErrors();
            $this->assertSame('Original', $portfolio->fresh()->nome_artistico);
            $this->assertSame([$valid->id], $user->categoriasArtisticas()->pluck('categorias_artisticas.id')->all());
        }
    }

    public function test_guest_navbar_keeps_login_and_registration(): void
    {
        $this->view('Components.navbar-content')->assertSee('Entrar')->assertSee('Cadastrar-se')->assertDontSee('data-account-menu', false);
    }

    public function test_artist_navbar_has_account_actions_and_no_post_button(): void
    {
        $usuario = $this->artista();
        PortfolioArtista::create(['id_usuario' => $usuario->id]);
        $this->actingAs($usuario);
        $view = $this->view('Components.navbar-content');
        $view->assertSee('data-account-menu', false)
            ->assertSee('Editar perfil')->assertSee('Formulário de orçamento')->assertSee('Meus orçamentos')->assertSee('Sair')
            ->assertSee('aria-label="Notificações"', false)->assertDontSee('data-bs-target="#postModal"', false)
            ->assertDontSee('Entrar')->assertDontSee('Cadastrar-se');
        $this->assertLessThan(strpos((string) $view, 'data-account-menu'), strpos((string) $view, 'id="notificacoesDropdown"'));
    }

    public function test_editor_has_three_tabs_and_fields_share_the_settings_form(): void
    {
        $usuario = $this->artista();
        $portfolio = PortfolioArtista::create(['id_usuario' => $usuario->id]);
        $portfolio->setRelation('posts', collect());
        $this->actingAs($usuario);
        $view = $this->view('usuarios.partials.portfolio_editor', [
            'usuario' => $usuario, 'portfolio' => $portfolio, 'categorias' => collect(),
            'categoriasSelecionadas' => [], 'categoriasPortfolio' => collect(),
            'corPrimariaPortfolio' => '#23774f', 'corSecundariaPortfolio' => '#91bdac',
            'estilosCardsCategorias' => PortfolioArtista::estilosCardsCategorias(), 'estiloCardCategorias' => 1,
            'errors' => new \Illuminate\Support\ViewErrorBag(),
        ]);
        $view->assertSee('Informações gerais')->assertSee('Cadastrar álbuns')->assertSee('Estilo')
            ->assertSee('Cadastre novas áreas de atuação somente se não encontrou nenhum opção compatível');
        $document = new \DOMDocument();
        @$document->loadHTML((string) $view);
        $xpath = new \DOMXPath($document);
        foreach (['nome_artistico', 'descricao', 'link_instagram', 'link_behance', 'link_tiktok', 'link_github', 'link_linkedin', 'cor_primaria_portfolio', 'cor_secundaria_portfolio', 'estilo_card_categorias_portfolio'] as $field) {
            $this->assertGreaterThan(0, $xpath->query('//*[@name="'.$field.'" and @form="portfolioSettingsForm"]')->length);
        }
        $this->assertSame(3, $xpath->query('//*[@role="tab"]')->length);
        $this->assertSame(0, $xpath->query('//form//form')->length);
        $this->assertSame(0, $xpath->query('//*[@id="portfolio-general-pane"]//*[@data-area-picker]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="portfolio-categories-pane"]//*[@data-area-picker]')->length);
        $this->assertSame(1, $xpath->query('//*[@role="combobox" and @aria-controls="portfolio-area-results"]')->length);
        $this->assertSame(0, $xpath->query('//input[@name="categorias[]" and @type="checkbox"]')->length);
    }

    public function test_optional_social_links_can_be_created_updated_and_cleared(): void
    {
        $usuario = $this->artista();
        $links = ['link_tiktok' => 'https://www.tiktok.com/@atelie', 'link_github' => 'https://github.com/atelie', 'link_linkedin' => 'https://www.linkedin.com/in/atelie'];
        $this->actingAs($usuario)->post(route('portfolio.store'), $links)->assertSessionHasNoErrors();
        $portfolio = $usuario->portfolioArtista()->first();
        $this->assertDatabaseHas('portfolio_artistas', ['id' => $portfolio->id] + $links);
        $this->put(route('portfolio.update', $portfolio), ['link_github' => 'https://github.com/novo'])
            ->assertSessionHasNoErrors();
        $this->assertSame('https://github.com/novo', $portfolio->fresh()->link_github);
        $this->assertSame($links['link_tiktok'], $portfolio->fresh()->link_tiktok);
        $this->put(route('portfolio.update', $portfolio), array_fill_keys(array_keys($links), ''))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('portfolio_artistas', ['id' => $portfolio->id, 'link_tiktok' => null, 'link_github' => null, 'link_linkedin' => null]);
    }

    public function test_invalid_or_unsafe_social_urls_are_rejected(): void
    {
        $usuario = $this->artista();
        $portfolio = PortfolioArtista::create(['id_usuario' => $usuario->id]);
        $this->actingAs($usuario)->put(route('portfolio.update', $portfolio), [
            'link_tiktok' => 'javascript:alert(1)', 'link_github' => 'ftp://github.com/user', 'link_linkedin' => str_repeat('x', 2001),
        ])->assertSessionHasErrors(['link_tiktok', 'link_github', 'link_linkedin']);
        $this->assertNull($portfolio->fresh()->link_tiktok);
    }

    public function test_another_artist_cannot_change_social_links(): void
    {
        $portfolio = PortfolioArtista::create(['id_usuario' => $this->artista()->id]);
        $this->actingAs($this->artista())->put(route('portfolio.update', $portfolio), ['link_github' => 'https://github.com/other'])->assertForbidden();
        $this->assertNull($portfolio->fresh()->link_github);
    }

    public function test_social_icons_only_render_configured_safe_links_and_phone(): void
    {
        $usuario = $this->artista();
        $usuario->telefone = '(55) 99999-1234';
        $portfolio = new PortfolioArtista(['link_github' => 'https://github.com/atelie', 'link_instagram' => 'javascript:alert(1)']);
        $view = $this->view('usuarios.partials.social_links', compact('usuario', 'portfolio'));
        $view->assertSee('aria-label="GitHub"', false)->assertSee('aria-label="WhatsApp"', false)
            ->assertSee('https://wa.me/5555999991234', false)->assertDontSee('aria-label="Instagram"', false)
            ->assertDontSee('aria-label="TikTok"', false)->assertDontSee('javascript:', false)
            ->assertSee('rel="noopener noreferrer"', false);
        $usuario->telefone = '';
        $this->view('usuarios.partials.social_links', ['usuario' => $usuario, 'portfolio' => null])
            ->assertDontSee('<a ', false);
    }

    public function test_social_links_migration_is_reversible_without_changing_other_fields(): void
    {
        $portfolio = PortfolioArtista::create(['id_usuario' => $this->artista()->id, 'nome_artistico' => 'Atelie']);
        $migration = require database_path('migrations/2026_10_06_000003_add_social_links_to_portfolio_artistas.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('portfolio_artistas', 'link_tiktok'));
        $this->assertSame('Atelie', $portfolio->fresh()->nome_artistico);
        $migration->up();
        foreach (['link_tiktok', 'link_github', 'link_linkedin'] as $field) {
            $this->assertTrue(Schema::hasColumn('portfolio_artistas', $field));
            $this->assertNull($portfolio->fresh()->{$field});
        }
    }
}
