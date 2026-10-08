<?php

namespace App\Http\Controllers;

use App\Models\CategoriaOrcamento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CategoriaOrcamentoController extends Controller
{
    private function portfolioAutorizado()
    {
        abort_unless((int) Auth::user()->tipo_usuario === 2, 403);

        return Auth::user()->portfolioArtista ?? abort(403);
    }

    private function dados(Request $request, $portfolio, ?CategoriaOrcamento $categoria = null): array
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:120', Rule::unique('categorias_orcamento')->where('id_portfolio_artista', $portfolio->id)->ignore($categoria?->id)],
            'ordem' => ['nullable', 'integer', 'min:0'],
        ]);
        $dados['ordem'] = $dados['ordem'] ?? 0;

        return $dados;
    }

    public function store(Request $request)
    {
        $portfolio = $this->portfolioAutorizado();
        $portfolio->categoriasOrcamento()->create($this->dados($request, $portfolio));

        return back()->with('success', 'Categoria de orçamento adicionada.');
    }

    public function update(Request $request, CategoriaOrcamento $categoriaOrcamento)
    {
        $portfolio = $this->portfolioAutorizado();
        abort_unless((int) $categoriaOrcamento->id_portfolio_artista === (int) $portfolio->id, 403);
        $categoriaOrcamento->update($this->dados($request, $portfolio, $categoriaOrcamento));

        return back()->with('success', 'Categoria de orçamento atualizada.');
    }

    public function destroy(CategoriaOrcamento $categoriaOrcamento)
    {
        $portfolio = $this->portfolioAutorizado();
        abort_unless((int) $categoriaOrcamento->id_portfolio_artista === (int) $portfolio->id, 403);
        if ($categoriaOrcamento->perguntas()->exists() || $categoriaOrcamento->propostas()->withTrashed()->exists()) {
            return back()->with('error', 'Esta categoria possui perguntas ou orçamentos vinculados e não pode ser excluída.');
        }
        $categoriaOrcamento->delete();

        return back()->with('success', 'Categoria de orçamento removida.');
    }
}
