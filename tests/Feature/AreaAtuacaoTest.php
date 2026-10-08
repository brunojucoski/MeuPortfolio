<?php

namespace Tests\Feature;

use App\Models\CategoriaArtistica;
use App\Models\Usuario;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AreaAtuacaoTest extends TestCase
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
        Schema::create('categorias_artisticas', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 45);
            $table->text('descricao');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    private function artista(): Usuario
    {
        return Usuario::create(['nome' => 'Artista de teste', 'tipo_usuario' => 2]);
    }

    public function test_only_authenticated_artists_can_search_and_create_areas(): void
    {
        $this->getJson(route('areas-atuacao.buscar', ['q' => 'pi']))->assertUnauthorized();
        $this->postJson(route('areas-atuacao.store'), ['nome' => 'Pintura', 'confirmacao' => true])->assertUnauthorized();
        foreach ([1, 3] as $tipo) {
            $this->actingAs(Usuario::create(['nome' => 'Outro perfil', 'tipo_usuario' => $tipo]));
            $this->getJson(route('areas-atuacao.buscar', ['q' => 'pi']))->assertForbidden();
            $this->postJson(route('areas-atuacao.store'), ['nome' => 'Pintura', 'confirmacao' => true])->assertForbidden();
        }
        $this->assertDatabaseCount('categorias_artisticas', 0);
    }

    public function test_search_starts_at_two_characters_and_ignores_deleted_areas(): void
    {
        CategoriaArtistica::create(['nome' => 'Pintura', 'descricao' => '']);
        $removed = CategoriaArtistica::create(['nome' => 'Pintura removida', 'descricao' => '']);
        $removed->delete();
        $this->actingAs($this->artista());
        foreach (['', 'p', '  p  '] as $term) {
            $this->getJson(route('areas-atuacao.buscar', ['q' => $term]))->assertOk()->assertExactJson(['data' => [], 'more' => false]);
        }
        $this->getJson(route('areas-atuacao.buscar', ['q' => '  PI  ']))->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.nome', 'Pintura');
    }

    public function test_search_is_bounded_and_treats_sql_wildcards_as_literal_characters(): void
    {
        $this->actingAs($this->artista());
        for ($i = 0; $i < 25; $i++) CategoriaArtistica::create(['nome' => 'Pintura ' . $i, 'descricao' => '']);
        $this->getJson(route('areas-atuacao.buscar', ['q' => 'pi']))->assertOk()->assertJsonCount(20, 'data')->assertJsonPath('more', true);
        CategoriaArtistica::create(['nome' => 'Arte 50%', 'descricao' => '']);
        CategoriaArtistica::create(['nome' => 'Arte 500', 'descricao' => '']);
        $this->getJson(route('areas-atuacao.buscar', ['q' => '50%']))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.nome', 'Arte 50%');
        $this->getJson(route('areas-atuacao.buscar', ['q' => ['pi']]))->assertUnprocessable()->assertJsonValidationErrors('q');
    }

    public function test_artist_can_create_an_area_without_linking_it_until_portfolio_save(): void
    {
        $this->actingAs($this->artista());
        $this->postJson(route('areas-atuacao.store'), ['nome' => '  Pintura   digital  ', 'confirmacao' => true, 'id' => 99])
            ->assertCreated()->assertJsonPath('data.nome', 'Pintura digital');
        $this->assertDatabaseHas('categorias_artisticas', ['nome' => 'Pintura digital', 'descricao' => '']);
        $this->assertDatabaseCount('categorias_artisticas', 1);
        $this->assertNotSame(99, CategoriaArtistica::first()->id);
    }

    public function test_creating_an_existing_area_reuses_it_without_overwriting_description(): void
    {
        $area = CategoriaArtistica::create(['nome' => 'Pintura digital', 'descricao' => 'Descrição do admin']);
        $this->actingAs($this->artista());
        $this->postJson(route('areas-atuacao.store'), ['nome' => '  PINTURA   DIGITAL ', 'confirmacao' => true])
            ->assertOk()->assertJsonPath('data.id', $area->id)->assertJsonPath('data.nome', $area->nome);
        $this->assertDatabaseCount('categorias_artisticas', 1);
        $this->assertSame('Descrição do admin', $area->fresh()->descricao);
    }

    public function test_new_area_requires_explicit_confirmation_and_a_valid_name(): void
    {
        $this->actingAs($this->artista());
        $this->postJson(route('areas-atuacao.store'), ['nome' => 'Pintura'])->assertUnprocessable()->assertJsonValidationErrors('confirmacao');
        foreach (['', '   ', 'p', str_repeat('a', 46), ['Pintura']] as $name) {
            $this->postJson(route('areas-atuacao.store'), ['nome' => $name, 'confirmacao' => true])->assertUnprocessable()->assertJsonValidationErrors('nome');
        }
        $this->assertDatabaseCount('categorias_artisticas', 0);
    }
}
