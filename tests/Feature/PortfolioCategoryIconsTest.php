<?php

namespace Tests\Feature;

use App\Models\CategoriaPostPortfolio;
use App\Models\PortfolioArtista;
use App\Models\Usuario;
use App\Support\PortfolioCategoryIcons;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PortfolioCategoryIconsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('usuarios', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->integer('tipo_usuario');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('portfolio_artistas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_usuario')->constrained('usuarios');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('categorias_posts_portfolio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_portfolio_artista')->constrained('portfolio_artistas');
            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->integer('ordem')->default(0);
            $table->timestamps();
        });
        (require database_path('migrations/2026_10_06_000001_add_icone_to_categorias_posts_portfolio.php'))->up();
    }

    private function artista(): PortfolioArtista
    {
        $usuario = Usuario::create(['nome' => 'Artista', 'tipo_usuario' => 2]);

        return PortfolioArtista::create(['id_usuario' => $usuario->id]);
    }

    public function test_classic_and_pixel_icons_can_be_saved(): void
    {
        $portfolio = $this->artista();
        foreach (['bi-palette', 'pixel-camera'] as $icone) {
            $this->actingAs($portfolio->usuario)->post(route('categorias-posts-portfolio.store'), [
                'nome' => $icone, 'icone' => $icone,
            ])->assertSessionHasNoErrors()->assertSessionHas('success');
            $this->assertDatabaseHas('categorias_posts_portfolio', [
                'id_portfolio_artista' => $portfolio->id, 'icone' => $icone,
            ]);
        }
    }

    public function test_icon_can_be_changed_and_removed(): void
    {
        $portfolio = $this->artista();
        $categoria = CategoriaPostPortfolio::create([
            'id_portfolio_artista' => $portfolio->id, 'nome' => 'Fotos', 'ordem' => 3,
        ]);
        foreach (['pixel-camera', ''] as $icone) {
            $this->actingAs($portfolio->usuario)->put(route('categorias-posts-portfolio.update', $categoria), [
                'nome' => 'Fotos', 'icone' => $icone, 'ordem' => '',
            ])->assertSessionHasNoErrors();
            $this->assertSame($icone ?: null, $categoria->fresh()->icone);
            $this->assertSame(3, $categoria->fresh()->ordem);
        }
    }

    public function test_unlisted_icon_is_rejected(): void
    {
        $portfolio = $this->artista();
        $this->actingAs($portfolio->usuario)->post(route('categorias-posts-portfolio.store'), [
            'nome' => 'Fotos', 'icone' => 'untrusted-icon',
        ])->assertSessionHasErrors('icone');
        $this->assertDatabaseCount('categorias_posts_portfolio', 0);
    }

    public function test_another_artist_cannot_change_category_icon(): void
    {
        $portfolio = $this->artista();
        $categoria = CategoriaPostPortfolio::create([
            'id_portfolio_artista' => $this->artista()->id, 'nome' => 'Fotos', 'icone' => 'bi-palette',
        ]);
        $this->actingAs($portfolio->usuario)->put(route('categorias-posts-portfolio.update', $categoria), [
            'nome' => 'Fotos', 'icone' => 'pixel-camera',
        ])->assertForbidden();
        $this->assertSame('bi-palette', $categoria->fresh()->icone);
    }

    public function test_all_pixel_icons_have_local_assets(): void
    {
        foreach (array_keys(PortfolioCategoryIcons::all()) as $icone) {
            if (str_starts_with($icone, 'pixel-')) {
                $this->assertFileExists(public_path('icons/pixelart/'.substr($icone, 6).'.svg'));
            }
        }
    }

    public function test_migration_preserves_existing_categories_and_is_reversible(): void
    {
        $migration = require database_path('migrations/2026_10_06_000001_add_icone_to_categorias_posts_portfolio.php');
        $migration->down();
        $portfolio = $this->artista();
        $categoria = CategoriaPostPortfolio::create(['id_portfolio_artista' => $portfolio->id, 'nome' => 'Antiga']);
        $migration->up();
        $this->assertNull($categoria->fresh()->icone);
        $migration->down();
        $this->assertFalse(Schema::hasColumn('categorias_posts_portfolio', 'icone'));
        $this->assertDatabaseHas('categorias_posts_portfolio', ['id' => $categoria->id, 'nome' => 'Antiga']);
    }
}
