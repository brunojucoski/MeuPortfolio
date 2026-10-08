<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PortfolioArtista;
use Illuminate\Support\Facades\Auth;
use App\Models\CategoriaArtistica;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;


class PortfolioArtistaController extends Controller
{
    private const CAMPOS_PORTFOLIO = [
        'nome_artistico',
        'descricao',
        'link_instagram',
        'link_behance',
        'link_tiktok',
        'link_github',
        'link_linkedin',
        'cor_primaria_portfolio',
        'cor_secundaria_portfolio',
        'estilo_card_categorias_portfolio',
    ];

    
    public function index()
    {
        $user = Auth::user();

        if ($user->tipo_usuario != 2) {
            abort(403, 'Acesso não autorizado.');
        }

        $portfolio = PortfolioArtista::where('id_usuario', $user->id)->first();

        return view('portfolio.index', compact('portfolio'));
    }

    public function create()
    {
        if (Auth::user()->tipo_usuario != 2) {
            abort(403);
        }

        return view('portfolio.create');
    }

    public function store(Request $request)
    {
        if (Auth::user()->tipo_usuario != 2) {
            abort(403);
        }

        $request->validate([
            'nome_artistico' => 'nullable|string|max:255',
            'descricao' => 'nullable|string',
            'link_instagram' => 'nullable|url:http,https|max:2000',
            'link_behance' => 'nullable|url:http,https|max:2000',
            'link_tiktok' => 'nullable|url:http,https|max:2000',
            'link_github' => 'nullable|url:http,https|max:2000',
            'link_linkedin' => 'nullable|url:http,https|max:2000',
            'cor_primaria_portfolio' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'cor_secundaria_portfolio' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'estilo_card_categorias_portfolio' => ['nullable', 'integer', \Illuminate\Validation\Rule::in(array_keys(PortfolioArtista::estilosCardsCategorias()))],
            'categorias' => 'array|nullable|max:100',
            'categorias.*' => ['integer', 'distinct', Rule::exists('categorias_artisticas', 'id')->whereNull('deleted_at')],
            'categorias_form' => 'nullable|boolean',
        ]);

        DB::transaction(function () use ($request) {
            PortfolioArtista::create(array_merge($request->only(self::CAMPOS_PORTFOLIO), [
                'id_usuario' => Auth::id(),
            ]));
            Auth::user()->categoriasArtisticas()->sync($request->input('categorias', []));
        });

        return redirect()->back()->with('success', 'Portfólio criado com sucesso!');
    }

    public function edit($id)
    {
        $portfolio = PortfolioArtista::findOrFail($id);

        if ($portfolio->id_usuario != Auth::id()) {
            abort(403);
        }

        return view('portfolio.edit', compact('portfolio'));
    }

    public function update(Request $request, $id)
    {
        $portfolio = PortfolioArtista::findOrFail($id);
        $user = Auth::user();
    
        if ($user->id !== $portfolio->id_usuario || $user->tipo_usuario != 2) {
            abort(403, 'Acesso não autorizado.');
        }
    
        $request->validate([
            'nome_artistico' => 'nullable|string|max:255',
            'descricao' => 'nullable|string',
            'link_instagram' => 'nullable|url:http,https|max:2000',
            'link_behance' => 'nullable|url:http,https|max:2000',
            'link_tiktok' => 'nullable|url:http,https|max:2000',
            'link_github' => 'nullable|url:http,https|max:2000',
            'link_linkedin' => 'nullable|url:http,https|max:2000',
            'cor_primaria_portfolio' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'cor_secundaria_portfolio' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'estilo_card_categorias_portfolio' => ['nullable', 'integer', \Illuminate\Validation\Rule::in(array_keys(PortfolioArtista::estilosCardsCategorias()))],
            'categorias' => 'array|nullable|max:100',
            'categorias.*' => ['integer', 'distinct', Rule::exists('categorias_artisticas', 'id')->whereNull('deleted_at')],
            'categorias_form' => 'nullable|boolean',
        ]);
    
        DB::transaction(function () use ($request, $portfolio) {
            $portfolio->update($request->only(self::CAMPOS_PORTFOLIO));
            if ($request->boolean('categorias_form')) {
                Auth::user()->categoriasArtisticas()->sync($request->input('categorias', []));
            }
        });
    
        return redirect()->back()->with('success', 'Portfólio atualizado com sucesso!');
    }




    public function destroy($id)
    {
        $portfolio = PortfolioArtista::findOrFail($id);

        if ($portfolio->id_usuario != Auth::id()) {
            abort(403);
        }

        $portfolio->delete();

        return redirect()->route('portfolio.index')->with('success', 'Portfólio removido com sucesso.');
    }
}
