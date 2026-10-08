<?php

namespace Tests\Feature;

use App\Models\CategoriaPostPortfolio;
use App\Models\PortfolioArtista;
use App\Models\Usuario;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PortfolioPostContextTest extends TestCase
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
            $table->timestamps();
        });
        Schema::create('posts_portfolio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_portfolio')->constrained('portfolio_artistas');
            $table->foreignId('id_categoria_post_portfolio')->nullable()->constrained('categorias_posts_portfolio');
            $table->string('nome');
            $table->text('descricao');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('posts_imgs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('posts_portfolio');
            $table->string('caminho_imagem');
            $table->timestamps();
        });
    }

    private function artista(): PortfolioArtista
    {
        return PortfolioArtista::create([
            'id_usuario' => Usuario::create(['nome' => 'Artista', 'tipo_usuario' => 2])->id,
        ]);
    }

    public function test_post_from_category_is_associated_and_returns_to_that_category(): void
    {
        $portfolio = $this->artista();
        $categoria = CategoriaPostPortfolio::create(['id_portfolio_artista' => $portfolio->id, 'nome' => 'Pinturas']);
        $url = route('usuarios.perfilPublico', ['id' => $portfolio->id_usuario, 'categoria' => $categoria->id]);
        $this->actingAs($portfolio->usuario)->from($url)->post(route('posts.store'), [
            'nome' => 'Obra', 'descricao' => 'Uma pintura', 'id_categoria_post_portfolio' => $categoria->id,
        ])->assertRedirect($url)->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertDatabaseHas('posts_portfolio', [
            'id_portfolio' => $portfolio->id, 'nome' => 'Obra', 'id_categoria_post_portfolio' => $categoria->id,
        ]);
    }

    public function test_post_from_primary_page_has_no_category(): void
    {
        $portfolio = $this->artista();
        CategoriaPostPortfolio::create(['id_portfolio_artista' => $portfolio->id, 'nome' => 'Pinturas']);
        $this->actingAs($portfolio->usuario)->post(route('posts.store'), [
            'nome' => 'Obra', 'descricao' => 'Um post geral', 'id_categoria_post_portfolio' => '',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertDatabaseHas('posts_portfolio', [
            'id_portfolio' => $portfolio->id, 'nome' => 'Obra', 'id_categoria_post_portfolio' => null,
        ]);
    }

    public function test_category_from_another_artist_cannot_be_used(): void
    {
        $portfolio = $this->artista();
        $categoria = CategoriaPostPortfolio::create(['id_portfolio_artista' => $this->artista()->id, 'nome' => 'Privada']);
        $this->actingAs($portfolio->usuario)->post(route('posts.store'), [
            'nome' => 'Obra', 'descricao' => 'Post', 'id_categoria_post_portfolio' => $categoria->id,
        ])->assertSessionHas('error');
        $this->assertDatabaseCount('posts_portfolio', 0);
    }

    public function test_category_context_is_hidden_and_does_not_require_selection(): void
    {
        $categoria = new CategoriaPostPortfolio(['nome' => 'Pinturas']);
        $categoria->id = 42;
        $this->view('usuarios.partials.post_creation_context', ['categoria' => $categoria])
            ->assertSee('type="hidden"', false)->assertSee('value="42"', false)
            ->assertSee('Pinturas')->assertDontSee('<select', false);
    }

    public function test_primary_context_has_empty_category(): void
    {
        $this->view('usuarios.partials.post_creation_context', ['categoria' => null])
            ->assertSee('value=""', false)->assertSee('Página principal (sem álbum)')
            ->assertDontSee('<select', false);
    }

    public function test_selected_images_are_uploaded_when_creating_and_editing_posts(): void
    {
        Storage::fake('public');
        $portfolio = $this->artista();
        $this->actingAs($portfolio->usuario)->post(route('posts.store'), [
            'nome' => 'Obra', 'descricao' => 'Com fotos', 'imagens' => [UploadedFile::fake()->image('foto.png')],
        ])->assertSessionHasNoErrors();
        $post = $portfolio->posts()->first();
        $image = $post->imagens()->first();
        Storage::disk('public')->assertExists($image->caminho_imagem);
        $this->put(route('posts.update', $post), [
            'nome' => 'Obra', 'descricao' => 'Com novas fotos', 'imagens' => [UploadedFile::fake()->image('nova.jpg')],
        ])->assertSessionHasNoErrors();
        $this->assertSame(2, $post->imagens()->count());
    }

    public function test_invalid_oversized_and_too_many_images_are_rejected(): void
    {
        Storage::fake('public');
        $portfolio = $this->artista();
        $this->actingAs($portfolio->usuario);
        foreach ([
            [UploadedFile::fake()->create('documento.pdf', 1, 'application/pdf')],
            [UploadedFile::fake()->image('grande.png')->size(8193)],
        ] as $images) {
            $this->post(route('posts.store'), ['nome' => 'Obra', 'descricao' => 'Fotos', 'imagens' => $images])
                ->assertSessionHasErrors('imagens.0');
        }
        $images = array_map(fn ($i) => UploadedFile::fake()->image("foto{$i}.png"), range(1, 21));
        $this->post(route('posts.store'), ['nome' => 'Obra', 'descricao' => 'Fotos', 'imagens' => $images])
            ->assertSessionHasErrors('imagens');
        $this->assertDatabaseCount('posts_portfolio', 0);
    }

    public function test_upload_control_keeps_native_multipart_field_and_accessible_preview(): void
    {
        $this->view('usuarios.partials.post_image_upload', ['inputId' => 'post_modal_imagens'])
            ->assertSee('name="imagens[]"', false)->assertSee('multiple', false)
            ->assertSee('data-post-image-upload', false)->assertSee('data-upload-previews', false)
            ->assertSee('aria-label="Imagens selecionadas"', false)->assertSee('role="alert"', false);
    }
}
