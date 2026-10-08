<?php

namespace App\Http\Controllers;

use App\Models\CategoriaPostPortfolio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use App\Support\PortfolioCategoryIcons;

class CategoriaPostPortfolioController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        if ((int) $user->tipo_usuario !== 2) {
            abort(403);
        }
        $portfolio = $user->portfolioArtista;
        if (! $portfolio) {
            return back()->with('error', 'Crie seu portfólio antes de adicionar álbuns.');
        }

        $validated = $request->validate([
            'nome' => 'required|string|max:255',
            'descricao' => 'nullable|string',
            'ordem' => 'nullable|integer',
            'icone' => ['nullable', Rule::in(array_keys(PortfolioCategoryIcons::all()))],
        ]);

        $validated['id_portfolio_artista'] = $portfolio->id;
        $validated['ordem'] = $validated['ordem'] ?? 0;

        CategoriaPostPortfolio::create($validated);

        return back()->with('success', 'Álbum criado com sucesso!');
    }

    public function update(Request $request, CategoriaPostPortfolio $categoriaPostPortfolio)
    {
        $this->authorizeOwn($categoriaPostPortfolio);

        $validated = $request->validate([
            'nome' => 'required|string|max:255',
            'descricao' => 'nullable|string',
            'ordem' => 'nullable|integer',
            'icone' => ['nullable', Rule::in(array_keys(PortfolioCategoryIcons::all()))],
        ]);

        $validated['ordem'] = $validated['ordem'] ?? $categoriaPostPortfolio->ordem;
        $categoriaPostPortfolio->update($validated);

        return back()->with('success', 'Álbum atualizado com sucesso!');
    }

    public function destroy(CategoriaPostPortfolio $categoriaPostPortfolio)
    {
        $this->authorizeOwn($categoriaPostPortfolio);

        $categoriaPostPortfolio->delete();

        return back()->with('success', 'Álbum removido. Os posts vinculados ficaram sem álbum.');
    }

    private function authorizeOwn(CategoriaPostPortfolio $categoriaPostPortfolio): void
    {
        $portfolio = $categoriaPostPortfolio->portfolio;
        if (! $portfolio || Auth::id() !== (int) $portfolio->id_usuario) {
            abort(403);
        }
    }
}
