<?php

namespace App\Http\Controllers;

use App\Models\PerguntaPropostaContrato;
use App\Models\RespostaPropostaPergunta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class PerguntaPropostaContratoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $user = Auth::user();
        if ((int) $user->tipo_usuario !== 2) {
            abort(403);
        }
        $portfolio = $user->portfolioArtista;
        if (! $portfolio) {
            return redirect()->route('perfil')->with('error', 'Crie seu portfólio antes de configurar o formulário de orçamento.');
        }

        $categoriasOrcamento = $portfolio->categoriasOrcamento()->with('perguntas')->get();

        return view('propostas.perguntas_proposta', compact('portfolio', 'categoriasOrcamento'));
    }

    public function store(Request $request)
    {
        $portfolio = $this->portfolioAutorizado();
        $rules = [
            'id_categoria_orcamento' => ['required', Rule::exists('categorias_orcamento', 'id')->where('id_portfolio_artista', $portfolio->id)],
            'tipo' => 'required|in:texto,opcoes,anexo',
            'titulo' => 'required|string|max:500',
            'ordem' => 'nullable|integer|min:0',
        ];
        if ($request->input('tipo') === 'opcoes') {
            $rules['opcoes'] = 'required|array|min:2|max:20';
            $rules['opcoes.*'] = 'required|string|max:255';
        }
        $validated = $request->validate($rules);

        $opcoes = null;
        if (($validated['tipo'] ?? '') === 'opcoes') {
            $opcoes = array_values(array_filter(array_map('trim', $request->input('opcoes', []))));
            if (count($opcoes) < 2) {
                return back()->withErrors(['opcoes' => 'Informe pelo menos duas opções de resposta.'])->withInput();
            }
        }

        PerguntaPropostaContrato::create([
            'id_portfolio_artista' => $portfolio->id,
            'id_categoria_orcamento' => $validated['id_categoria_orcamento'],
            'tipo' => $validated['tipo'],
            'titulo' => $validated['titulo'],
            'opcoes_json' => $opcoes,
            'ordem' => $validated['ordem'] ?? 0,
        ]);

        return back()->with('success', 'Pergunta adicionada.')
            ->with('categoria_orcamento_aberta', $validated['id_categoria_orcamento']);
    }

    public function update(Request $request, PerguntaPropostaContrato $perguntaPropostaContrato)
    {
        $portfolio = $this->portfolioAutorizado();
        if ((int) $perguntaPropostaContrato->id_portfolio_artista !== (int) $portfolio->id) {
            abort(403);
        }

        $rules = [
            'id_categoria_orcamento' => ['required', Rule::exists('categorias_orcamento', 'id')->where('id_portfolio_artista', $portfolio->id)],
            'titulo' => 'required|string|max:500',
            'ordem' => 'nullable|integer|min:0',
        ];
        if ($perguntaPropostaContrato->tipo === 'opcoes') {
            $rules['opcoes'] = 'required|array|min:2|max:20';
            $rules['opcoes.*'] = 'required|string|max:255';
        }
        $validated = $request->validate($rules);

        $data = [
            'id_categoria_orcamento' => $validated['id_categoria_orcamento'],
            'titulo' => $validated['titulo'],
            'ordem' => $validated['ordem'] ?? $perguntaPropostaContrato->ordem,
        ];
        if ($perguntaPropostaContrato->tipo === 'opcoes') {
            $opcoes = array_values(array_filter(array_map('trim', $request->input('opcoes', []))));
            if (count($opcoes) < 2) {
                return back()->withErrors(['opcoes' => 'Informe pelo menos duas opções.'])->withInput();
            }
            $data['opcoes_json'] = $opcoes;
        }

        $perguntaPropostaContrato->update($data);

        return back()->with('success', 'Pergunta atualizada.')
            ->with('categoria_orcamento_aberta', $validated['id_categoria_orcamento']);
    }

    public function destroy(PerguntaPropostaContrato $perguntaPropostaContrato)
    {
        $portfolio = $this->portfolioAutorizado();
        if ((int) $perguntaPropostaContrato->id_portfolio_artista !== (int) $portfolio->id) {
            abort(403);
        }
        if (RespostaPropostaPergunta::where('id_pergunta', $perguntaPropostaContrato->id)->exists()) {
            return back()->with('error', 'Não é possível excluir: já existem propostas com respostas a esta pergunta.')
                ->with('categoria_orcamento_aberta', $perguntaPropostaContrato->id_categoria_orcamento);
        }
        $perguntaPropostaContrato->delete();

        return back()->with('success', 'Pergunta removida.')
            ->with('categoria_orcamento_aberta', $perguntaPropostaContrato->id_categoria_orcamento);
    }

    private function portfolioAutorizado()
    {
        $user = Auth::user();
        if ((int) $user->tipo_usuario !== 2) {
            abort(403);
        }
        $portfolio = $user->portfolioArtista;
        if (! $portfolio) {
            abort(403, 'Portfólio não encontrado.');
        }

        return $portfolio;
    }
}
