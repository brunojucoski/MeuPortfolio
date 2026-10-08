<?php

namespace App\Http\Controllers;

use App\Models\CategoriaArtistica;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AreaAtuacaoController extends Controller
{
    public function buscar(Request $request)
    {
        abort_unless((int) $request->user()->tipo_usuario === 2, 403);
        $data = $request->validate(['q' => 'nullable|string|max:45']);
        $termo = Str::squish($data['q'] ?? '');
        if (mb_strlen($termo) < 2) return response()->json(['data' => [], 'more' => false]);
        $busca = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], Str::lower($termo));
        $areas = CategoriaArtistica::whereRaw("LOWER(nome) LIKE ? ESCAPE '!'", ['%' . $busca . '%'])
            ->orderBy('nome')->orderBy('id')->limit(21)->get(['id', 'nome']);
        return response()->json(['data' => $areas->take(20)->values(), 'more' => $areas->count() > 20]);
    }

    public function store(Request $request)
    {
        abort_unless((int) $request->user()->tipo_usuario === 2, 403);
        if (is_string($request->input('nome'))) $request->merge(['nome' => Str::squish($request->input('nome'))]);
        $data = $request->validate([
            'nome' => 'required|string|min:2|max:45',
            'confirmacao' => 'required|accepted',
        ], [
            'nome.min' => 'Informe pelo menos dois caracteres para a área de atuação.',
            'nome.max' => 'O nome da área de atuação deve ter até 45 caracteres.',
            'confirmacao.accepted' => 'Confirme o cadastro de uma nova área de atuação.',
        ]);
        try {
            // Serialize artist submissions so simultaneous requests reuse an existing area.
            $area = Cache::lock('areas-atuacao:cadastro', 10)->block(3, function () use ($data) {
                return CategoriaArtistica::whereRaw('LOWER(TRIM(nome)) = ?', [Str::lower($data['nome'])])->first()
                    ?? CategoriaArtistica::create(['nome' => $data['nome'], 'descricao' => '']);
            });
        } catch (LockTimeoutException $error) {
            return response()->json(['message' => 'Não foi possível cadastrar agora. Tente novamente.'], 503);
        }
        return response()->json(['data' => ['id' => $area->id, 'nome' => $area->nome]], $area->wasRecentlyCreated ? 201 : 200);
    }
}
