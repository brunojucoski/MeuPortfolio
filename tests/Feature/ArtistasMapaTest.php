<?php

namespace Tests\Feature;

use App\Models\CategoriaArtistica;
use App\Models\PortfolioArtista;
use App\Models\Usuario;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ArtistasMapaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        // SQLite omits the math extension in XAMPP; mirror MySQL's query functions.
        foreach (['sin' => 'sin', 'cos' => 'cos', 'radians' => 'deg2rad', 'power' => 'pow'] as $sql => $php) {
            DB::connection()->getPdo()->sqliteCreateFunction($sql, $php, $sql === 'power' ? 2 : 1);
        }
        Schema::create('usuarios', function (Blueprint $table) {
            $table->id();
            $table->string('nome')->nullable();
            $table->integer('tipo_usuario');
            foreach (['cidade', 'bairro', 'foto_perfil'] as $column) {
                $table->string($column)->nullable();
            }
            $table->date('data_nasc')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('portfolio_artistas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_usuario');
            $table->string('nome_artistico')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('categorias_artisticas', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('categorias_usuarios', function (Blueprint $table) {
            $table->unsignedBigInteger('id_usuario');
            $table->unsignedBigInteger('id_categoria');
        });
        Schema::create('feedbacks_artistas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_artista');
            $table->integer('nota');
            $table->timestamps();
            $table->softDeletes();
        });
        (require database_path('migrations/2026_10_06_000002_add_map_search_index_to_usuarios.php'))->up();
    }

    private function artista(array $attributes = []): Usuario
    {
        return Usuario::create(array_merge([
            'nome' => 'Artista', 'tipo_usuario' => 2, 'cidade' => 'Curitiba',
            'latitude' => -25.4284, 'longitude' => -49.2733,
        ], $attributes));
    }

    private function mapa(array $parameters = [])
    {
        return $this->getJson(route('usuarios.mapa', array_merge([
            'latitude' => -25.4284, 'longitude' => -49.2733,
        ], $parameters)));
    }

    public function test_exact_radius_excludes_distant_artists_and_bounding_box_corners(): void
    {
        $center = $this->artista();
        $near = $this->artista(['latitude' => -25.31]); // About 13 km.
        $this->artista(['latitude' => -25.28]); // About 16.5 km.
        $this->artista(['latitude' => -25.32, 'longitude' => -49.15]); // Box corner, outside circle.
        $response = $this->mapa(['raio_km' => 1000])->assertOk()->assertJsonPath('raio_km', 15);
        $this->assertSame([$center->id, $near->id], array_column($response->json('artistas'), 'id'));
    }

    public function test_moving_center_changes_the_search_area(): void
    {
        $first = $this->artista();
        $second = $this->artista(['latitude' => -23.55, 'longitude' => -46.63]);
        $this->mapa()->assertJsonCount(1, 'artistas')->assertJsonPath('artistas.0.id', $first->id);
        $this->mapa(['latitude' => -23.55, 'longitude' => -46.63])
            ->assertJsonCount(1, 'artistas')->assertJsonPath('artistas.0.id', $second->id);
    }

    public function test_fifteen_kilometer_boundary_is_enforced(): void
    {
        $inside = $this->artista(['latitude' => -25.4284 + rad2deg(14.999 / 6371.0088)]);
        $this->artista(['latitude' => -25.4284 + rad2deg(15.001 / 6371.0088)]);
        $this->mapa()->assertJsonCount(1, 'artistas')->assertJsonPath('artistas.0.id', $inside->id);
    }

    public function test_city_and_category_filters_are_kept(): void
    {
        $category = CategoriaArtistica::create(['nome' => 'Pintura']);
        $match = $this->artista();
        $match->categoriasArtisticas()->attach($category);
        $this->artista();
        $otherCity = $this->artista(['cidade' => 'Colombo']);
        $otherCity->categoriasArtisticas()->attach($category);
        $this->mapa(['cidade' => 'Curitiba', 'categoria' => $category->id])
            ->assertJsonCount(1, 'artistas')->assertJsonPath('artistas.0.id', $match->id)
            ->assertJsonPath('artistas.0.categorias.0', 'Pintura');
    }

    public function test_only_active_artists_with_valid_coordinates_are_returned(): void
    {
        $valid = $this->artista();
        $this->artista(['tipo_usuario' => 3]);
        $this->artista(['latitude' => null]);
        $this->artista(['longitude' => null]);
        $this->artista(['longitude' => 190]);
        $this->artista(['nome' => null]);
        $this->artista()->delete();
        $this->mapa()->assertJsonCount(1, 'artistas')->assertJsonPath('artistas.0.id', $valid->id);
    }

    public function test_empty_area_is_a_successful_empty_response(): void
    {
        $this->mapa()->assertOk()->assertJsonCount(0, 'artistas')->assertJsonPath('tem_mais', false);
    }

    public function test_coordinates_and_filters_are_validated(): void
    {
        $this->getJson(route('usuarios.mapa'))->assertUnprocessable()->assertJsonValidationErrors(['latitude', 'longitude']);
        $this->mapa(['latitude' => 91, 'longitude' => -181, 'categoria' => 'abc', 'cidade' => str_repeat('a', 256)])
            ->assertUnprocessable()->assertJsonValidationErrors(['latitude', 'longitude', 'categoria', 'cidade']);
    }

    public function test_results_are_capped_and_ordered_by_distance(): void
    {
        $farthest = $this->artista(['latitude' => -25.31]);
        for ($i = 0; $i < 200; $i++) {
            $this->artista();
        }
        $response = $this->mapa()->assertOk()->assertJsonCount(200, 'artistas')
            ->assertJsonPath('limite', 200)->assertJsonPath('tem_mais', true);
        $this->assertNotContains($farthest->id, array_column($response->json('artistas'), 'id'));
    }

    public function test_map_returns_profile_photo_and_aggregated_ratings(): void
    {
        $artist = $this->artista(['foto_perfil' => 'perfis/foto.png']);
        $artist->forceFill(['foto_perfil' => 'perfis/foto.png'])->save();
        $portfolio = PortfolioArtista::create(['id_usuario' => $artist->id, 'nome_artistico' => 'Atelie']);
        DB::table('feedbacks_artistas')->insert([
            ['id_artista' => $portfolio->id, 'nota' => 4, 'deleted_at' => null],
            ['id_artista' => $portfolio->id, 'nota' => 5, 'deleted_at' => null],
            ['id_artista' => $portfolio->id, 'nota' => 1, 'deleted_at' => now()],
        ]);
        $this->mapa()->assertJsonPath('artistas.0.nome_artistico', 'Atelie')
            ->assertJsonPath('artistas.0.foto', asset('storage/perfis/foto.png'))
            ->assertJsonPath('artistas.0.avaliacao_media', '4,5')
            ->assertJsonPath('artistas.0.avaliacao_total', 2)
            ->assertJsonPath('artistas.0.perfil_url', route('usuarios.perfilPublico', $artist->id));
    }

    public function test_longitude_wrap_and_polar_searches_work(): void
    {
        $wrapped = $this->artista(['latitude' => 0, 'longitude' => -179.95]);
        $this->mapa(['latitude' => 0, 'longitude' => 179.95])->assertJsonPath('artistas.0.id', $wrapped->id);
        $polar = $this->artista(['latitude' => 89.99, 'longitude' => 120]);
        $this->mapa(['latitude' => 90, 'longitude' => 0])->assertJsonPath('artistas.0.id', $polar->id);
    }

    public function test_list_page_uses_switch_and_no_longer_embeds_every_map_artist(): void
    {
        $artist = $this->artista();
        $this->get(route('usuarios.publico', ['visualizacao_artistas' => 'mapa']))
            ->assertOk()->assertSee('role="switch"', false)->assertSee('Lista')->assertSee('Mapa')
            ->assertSee('id="artistas-map-config"', false)->assertDontSee('id="artistas-map-data"', false)
            ->assertDontSee('Visualizar em mapa')->assertViewHas('centroMapa', [(float) $artist->latitude, (float) $artist->longitude]);
    }

    public function test_map_index_migration_is_reversible(): void
    {
        $migration = require database_path('migrations/2026_10_06_000002_add_map_search_index_to_usuarios.php');
        $migration->down();
        $this->assertEmpty(DB::select("SELECT name FROM sqlite_master WHERE type = 'index' AND name = 'usuarios_mapa_localizacao_index'"));
        $migration->up();
        $this->assertCount(1, DB::select("SELECT name FROM sqlite_master WHERE type = 'index' AND name = 'usuarios_mapa_localizacao_index'"));
    }
}
