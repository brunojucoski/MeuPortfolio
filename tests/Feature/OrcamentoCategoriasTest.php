<?php

namespace Tests\Feature;

use App\Models\CategoriaOrcamento;
use App\Models\PerguntaPropostaContrato;
use App\Models\PortfolioArtista;
use App\Models\PropostaContrato;
use App\Models\Usuario;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrcamentoCategoriasTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        // Isolate the budget flow from unrelated legacy MySQL migrations.
        Schema::create('usuarios', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('email')->nullable();
            $table->string('senha')->nullable();
            $table->string('telefone')->nullable();
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
        Schema::create('proposta_contrato', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_artista')->constrained('portfolio_artistas');
            $table->foreignId('id_usuario_avaliador')->constrained('usuarios');
            $table->string('status')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        foreach (['2026_05_02_120000_create_perguntas_proposta_contrato_table', '2026_05_02_120001_create_respostas_proposta_pergunta_table', '2026_05_02_140000_create_timeline_proposta_contrato_table', '2025_06_03_043011_create_notificacaos_table'] as $migration) {
            (require database_path('migrations/'.$migration.'.php'))->up();
        }
        if ($this->name() !== 'test_existing_questions_and_proposals_are_migrated_to_general') {
            (require database_path('migrations/2026_10_06_000000_create_categorias_orcamento.php'))->up();
        }
    }

    private function artista(): PortfolioArtista
    {
        $usuario = Usuario::create(['nome' => 'Artista', 'tipo_usuario' => 2]);

        return PortfolioArtista::create(['id_usuario' => $usuario->id]);
    }

    private function categoria(PortfolioArtista $portfolio, string $nome): CategoriaOrcamento
    {
        return $portfolio->categoriasOrcamento()->create(['nome' => $nome]);
    }

    private function pergunta(CategoriaOrcamento $categoria, string $tipo = 'texto'): PerguntaPropostaContrato
    {
        return PerguntaPropostaContrato::create([
            'id_portfolio_artista' => $categoria->id_portfolio_artista,
            'id_categoria_orcamento' => $categoria->id,
            'titulo' => 'Detalhes', 'tipo' => $tipo,
            'opcoes_json' => $tipo === 'opcoes' ? ['Primeira', 'Segunda'] : null,
        ]);
    }

    public function test_single_category_is_selected_automatically(): void
    {
        $portfolio = $this->artista();
        $categoria = $this->categoria($portfolio, 'Evento');
        $pergunta = $this->pergunta($categoria);
        $solicitante = Usuario::create(['nome' => 'Solicitante', 'tipo_usuario' => 3]);

        $this->actingAs($solicitante)->post(route('propostas.store'), [
            'id_artista' => $portfolio->id, 'respostas' => [$pergunta->id => 'Casamento'],
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertDatabaseHas('proposta_contrato', ['id_categoria_orcamento' => $categoria->id, 'categoria_orcamento_nome' => 'Evento']);
    }

    public function test_multiple_categories_require_a_choice(): void
    {
        $portfolio = $this->artista();
        $this->pergunta($this->categoria($portfolio, 'Evento'));
        $this->pergunta($this->categoria($portfolio, 'Retrato'));

        $this->post(route('propostas.store'), ['id_artista' => $portfolio->id])
            ->assertSessionHasErrors('id_categoria_orcamento');
        $this->assertDatabaseCount('proposta_contrato', 0);
    }

    public function test_only_selected_category_answers_are_saved(): void
    {
        $portfolio = $this->artista();
        $categoria = $this->categoria($portfolio, 'Evento');
        $pergunta = $this->pergunta($categoria);
        $outraPergunta = $this->pergunta($this->categoria($portfolio, 'Retrato'), 'anexo');
        $solicitante = Usuario::create(['nome' => 'Solicitante', 'tipo_usuario' => 3]);

        $this->actingAs($solicitante)->post(route('propostas.store'), [
            'id_artista' => $portfolio->id, 'id_categoria_orcamento' => $categoria->id,
            'respostas' => [$pergunta->id => 'Evento', $outraPergunta->id => 'Ignorar'],
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertDatabaseCount('respostas_proposta_pergunta', 1);
        $this->assertDatabaseHas('respostas_proposta_pergunta', ['id_pergunta' => $pergunta->id]);
    }

    public function test_foreign_category_cannot_be_used_for_a_request_or_question(): void
    {
        $portfolio = $this->artista();
        $this->pergunta($this->categoria($portfolio, 'Evento'));
        $categoriaAlheia = $this->categoria($this->artista(), 'Outra');
        $this->pergunta($categoriaAlheia);

        $this->post(route('propostas.store'), ['id_artista' => $portfolio->id, 'id_categoria_orcamento' => $categoriaAlheia->id])
            ->assertSessionHasErrors('id_categoria_orcamento');
        $this->actingAs($portfolio->usuario)->post(route('perguntas-proposta.store'), [
            'id_categoria_orcamento' => $categoriaAlheia->id, 'tipo' => 'texto', 'titulo' => 'Pergunta',
        ])->assertSessionHasErrors('id_categoria_orcamento');
    }

    public function test_category_edit_is_restricted_to_its_owner(): void
    {
        $portfolio = $this->artista();
        $categoria = $this->categoria($this->artista(), 'Outra');
        $this->actingAs($portfolio->usuario)->put(route('categorias-orcamento.update', $categoria), ['nome' => 'Invadida'])->assertForbidden();
        $this->assertDatabaseHas('categorias_orcamento', ['id' => $categoria->id, 'nome' => 'Outra']);
    }

    public function test_empty_categories_are_not_available_and_used_categories_cannot_be_deleted(): void
    {
        $portfolio = $this->artista();
        $categoria = $this->categoria($portfolio, 'Vazia');
        $this->post(route('propostas.store'), ['id_artista' => $portfolio->id, 'id_categoria_orcamento' => $categoria->id])
            ->assertSessionHasErrors('id_categoria_orcamento');
        $this->pergunta($categoria);
        $this->actingAs($portfolio->usuario)->delete(route('categorias-orcamento.destroy', $categoria))->assertSessionHas('error');
        $this->assertDatabaseHas('categorias_orcamento', ['id' => $categoria->id]);
    }

    public function test_existing_questions_and_proposals_are_migrated_to_general(): void
    {
        $migration = require database_path('migrations/2026_10_06_000000_create_categorias_orcamento.php');
        $portfolio = $this->artista();
        $pergunta = PerguntaPropostaContrato::create(['id_portfolio_artista' => $portfolio->id, 'tipo' => 'texto', 'titulo' => 'Antiga']);
        $proposta = PropostaContrato::create(['id_artista' => $portfolio->id, 'id_usuario_avaliador' => $portfolio->id_usuario]);
        $migration->up();

        $categoria = CategoriaOrcamento::firstOrFail();
        $this->assertSame('Geral', $categoria->nome);
        $this->assertEquals($categoria->id, $pergunta->fresh()->id_categoria_orcamento);
        $this->assertEquals($categoria->id, $proposta->fresh()->id_categoria_orcamento);
    }

    public function test_invalid_option_is_rejected_before_creating_a_proposal(): void
    {
        $portfolio = $this->artista();
        $categoria = $this->categoria($portfolio, 'Evento');
        $pergunta = $this->pergunta($categoria, 'opcoes');
        $this->post(route('propostas.store'), [
            'id_artista' => $portfolio->id, 'id_categoria_orcamento' => $categoria->id,
            'respostas' => [$pergunta->id => 100],
        ])->assertSessionHas('error');
        $this->assertDatabaseCount('proposta_contrato', 0);
    }

    public function test_guest_can_submit_a_categorized_budget(): void
    {
        $portfolio = $this->artista();
        $categoria = $this->categoria($portfolio, 'Evento');
        $pergunta = $this->pergunta($categoria);
        Usuario::create(['nome' => 'Visitante', 'tipo_usuario' => 3, 'email' => Usuario::EMAIL_VISITANTE_NAO_IDENTIFICADO]);
        $this->post(route('propostas.store'), [
            'id_artista' => $portfolio->id, 'respostas' => [$pergunta->id => 'Evento'],
        ])->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertDatabaseHas('proposta_contrato', ['id_categoria_orcamento' => $categoria->id]);
        $this->assertDatabaseCount('timeline_proposta_contrato', 0);
    }

    public function test_artist_can_create_a_category_and_link_a_question(): void
    {
        $portfolio = $this->artista();
        $this->actingAs($portfolio->usuario)->post(route('categorias-orcamento.store'), ['nome' => 'Retrato', 'ordem' => ''])
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $categoria = CategoriaOrcamento::firstOrFail();
        $this->assertSame(0, $categoria->ordem);
        $this->post(route('perguntas-proposta.store'), [
            'id_categoria_orcamento' => $categoria->id, 'tipo' => 'texto', 'titulo' => 'Tamanho?',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('perguntas_proposta_contrato', ['id_categoria_orcamento' => $categoria->id, 'titulo' => 'Tamanho?']);
    }

    private function paginaPerguntas(PortfolioArtista $portfolio)
    {
        $portfolio->setRelation('categoriasPostsPortfolio', collect());
        $usuario = $portfolio->usuario->setRelation('portfolioArtista', $portfolio);

        return $this->actingAs($usuario)->get(route('perguntas-proposta.index'));
    }

    private function xpathPagina($response): \DOMXPath
    {
        $document = new \DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());

        return new \DOMXPath($document);
    }

    public function test_question_categories_start_collapsed_and_questions_are_grouped_and_ordered(): void
    {
        $portfolio = $this->artista();
        $evento = $this->categoria($portfolio, 'Evento');
        $retrato = $this->categoria($portfolio, 'Retrato');
        $later = $this->pergunta($evento);
        $later->update(['titulo' => 'Pergunta posterior', 'ordem' => 8]);
        $first = $this->pergunta($evento);
        $first->update(['titulo' => 'Pergunta inicial', 'ordem' => 1]);
        $this->pergunta($retrato)->update(['titulo' => 'Pergunta de retrato']);
        $this->pergunta($this->categoria($this->artista(), 'Outra'))->update(['titulo' => 'Pergunta alheia']);

        $response = $this->paginaPerguntas($portfolio)->assertOk()->assertDontSee('Pergunta alheia');
        $xpath = $this->xpathPagina($response);
        $this->assertSame(2, $xpath->query('//button[@data-bs-toggle="collapse" and @aria-expanded="false"]')->length);
        $this->assertSame(0, $xpath->query('//article[contains(@class,"orcamento-categoria-card")]//div[contains(concat(" ", @class, " ")," show ")]')->length);
        $eventRows = $xpath->query('//*[@id="perguntasCategoria'.$evento->id.'"]//*[@data-pergunta-id]');
        $this->assertSame(2, $eventRows->length);
        $this->assertSame((string) $first->id, $eventRows->item(0)->getAttribute('data-pergunta-id'));
        $this->assertSame((string) $later->id, $eventRows->item(1)->getAttribute('data-pergunta-id'));
        $this->assertSame(1, $xpath->query('//*[@id="perguntasCategoria'.$retrato->id.'"]//*[@data-pergunta-id]')->length);
        $this->assertSame('9', $xpath->query('//*[@data-nova-pergunta-categoria="'.$evento->id.'"]')->item(0)->getAttribute('data-proxima-ordem'));
    }

    public function test_question_creation_buttons_only_exist_inside_category_cards(): void
    {
        $portfolio = $this->artista();
        $this->categoria($portfolio, 'Evento');
        $this->categoria($portfolio, 'Retrato');
        $xpath = $this->xpathPagina($this->paginaPerguntas($portfolio)->assertOk());

        $this->assertSame(2, $xpath->query('//button[@data-nova-pergunta-categoria]')->length);
        $this->assertSame(2, $xpath->query('//article[@data-orcamento-categoria]//button[@data-nova-pergunta-categoria]')->length);
        $this->assertSame(0, $xpath->query('//*[@id="filtroCategoriaOrcamento"]')->length);
        foreach (['formNovaPergunta', 'editarPerguntaPropostaForm'] as $form) {
            $this->assertSame(1, $xpath->query('//form[@id="'.$form.'"]//input[@type="hidden" and @name="id_categoria_orcamento"]')->length);
            $this->assertSame(0, $xpath->query('//form[@id="'.$form.'"]//select[@name="id_categoria_orcamento"]')->length);
        }
    }

    public function test_question_page_loads_bootstrap_javascript_only_once(): void
    {
        $portfolio = $this->artista();
        $this->categoria($portfolio, 'Evento');
        $xpath = $this->xpathPagina($this->paginaPerguntas($portfolio)->assertOk());

        $this->assertSame(1, $xpath->query('//script[@src and contains(@src, "/bootstrap") and contains(@src, "/js/")]')->length);
    }

    public function test_empty_category_has_its_own_creation_button_and_empty_state(): void
    {
        $portfolio = $this->artista();
        $categoria = $this->categoria($portfolio, 'Vazia');
        $response = $this->paginaPerguntas($portfolio)->assertOk()->assertSee('Nenhuma pergunta nesta categoria.');
        $xpath = $this->xpathPagina($response);
        $button = $xpath->query('//button[@data-nova-pergunta-categoria="'.$categoria->id.'"]')->item(0);
        $this->assertSame('Vazia', $button->getAttribute('data-categoria-nome'));
        $this->assertSame('0', $button->getAttribute('data-proxima-ordem'));
        $this->assertSame(0, $xpath->query('//*[contains(concat(" ", @class, " ")," perguntas-list-head ")]')->length);
    }

    public function test_page_without_categories_only_offers_category_creation(): void
    {
        $response = $this->paginaPerguntas($this->artista())->assertOk()->assertSee('Nenhuma categoria cadastrada.');
        $xpath = $this->xpathPagina($response);
        $this->assertSame(1, $xpath->query('//button[@aria-label="Nova categoria"]')->length);
        $this->assertSame(0, $xpath->query('//button[@data-nova-pergunta-categoria]')->length);
    }

    public function test_creating_a_question_reopens_only_its_category(): void
    {
        $portfolio = $this->artista();
        $categoria = $this->categoria($portfolio, 'Evento');
        $this->categoria($portfolio, 'Retrato');
        $this->actingAs($portfolio->usuario)->from(route('perguntas-proposta.index'))->post(route('perguntas-proposta.store'), [
            '_form' => 'nova_pergunta', 'id_categoria_orcamento' => $categoria->id,
            'tipo' => 'texto', 'titulo' => 'Local do evento?',
        ])->assertSessionHasNoErrors()->assertSessionHas('categoria_orcamento_aberta', $categoria->id);
        $xpath = $this->xpathPagina($this->paginaPerguntas($portfolio)->assertOk());
        $this->assertSame(1, $xpath->query('//button[@data-bs-toggle="collapse" and @aria-expanded="true"]')->length);
        $this->assertSame('true', $xpath->query('//*[@id="categoriaOrcamentoToggle'.$categoria->id.'"]')->item(0)->getAttribute('aria-expanded'));
    }

    public function test_validation_errors_preserve_the_category_and_modal_context(): void
    {
        $portfolio = $this->artista();
        $categoria = $this->categoria($portfolio, 'Evento');
        $this->actingAs($portfolio->usuario)->from(route('perguntas-proposta.index'))->post(route('perguntas-proposta.store'), [
            '_form' => 'nova_pergunta', 'id_categoria_orcamento' => $categoria->id,
            'tipo' => 'opcoes', 'titulo' => 'Tamanho?', 'opcoes' => ['Pequeno'],
        ])->assertSessionHasErrors('opcoes')->assertSessionHasInput('id_categoria_orcamento', $categoria->id);
        $xpath = $this->xpathPagina($this->paginaPerguntas($portfolio)->assertOk());
        $this->assertSame('true', $xpath->query('//*[@id="categoriaOrcamentoToggle'.$categoria->id.'"]')->item(0)->getAttribute('aria-expanded'));
        $this->assertDatabaseCount('perguntas_proposta_contrato', 0);
    }

    public function test_editing_questions_keeps_category_ownership_validation(): void
    {
        $portfolio = $this->artista();
        $categoria = $this->categoria($portfolio, 'Evento');
        $pergunta = $this->pergunta($categoria);
        $foreign = $this->categoria($this->artista(), 'Alheia');
        $this->actingAs($portfolio->usuario)->put(route('perguntas-proposta.update', $pergunta), [
            'titulo' => 'Alterada', 'id_categoria_orcamento' => $foreign->id,
        ])->assertSessionHasErrors('id_categoria_orcamento');
        $this->assertEquals($categoria->id, $pergunta->fresh()->id_categoria_orcamento);
        $this->put(route('perguntas-proposta.update', $pergunta), [
            'titulo' => 'Alterada', 'id_categoria_orcamento' => $categoria->id,
        ])->assertSessionHasNoErrors()->assertSessionHas('categoria_orcamento_aberta', $categoria->id);
    }

    public function test_deleting_a_question_preserves_the_open_category(): void
    {
        $portfolio = $this->artista();
        $categoria = $this->categoria($portfolio, 'Evento');
        $pergunta = $this->pergunta($categoria);
        $this->actingAs($portfolio->usuario)->delete(route('perguntas-proposta.destroy', $pergunta))
            ->assertSessionHas('success')->assertSessionHas('categoria_orcamento_aberta', $categoria->id);
        $this->assertDatabaseMissing('perguntas_proposta_contrato', ['id' => $pergunta->id]);
    }
}
