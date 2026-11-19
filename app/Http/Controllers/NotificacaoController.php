<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\PropostaContrato; 
use App\Models\Usuario;
use App\Models\Notificacao;
use App\Models\TipoUsuario;

class NotificacaoController extends Controller
{
public function index()
{
    $usuario = Auth::user();

    $notificacoes = Notificacao::where('usuario_id', $usuario->id)
        ->where('lida', false)
        ->with('proposta', 'proposta.usuarioAvaliador')
        ->latest()
        ->take(5)
        ->get();

    return response()->json($notificacoes);
}

public function marcarComoLida($id)
{
    $notificacao = Notificacao::where('id', $id)
        ->where('usuario_id', auth()->id())
        ->firstOrFail();

    $notificacao->lida = true;
    $notificacao->save();

    return response()->json(['status' => 'ok']);
}

public function lerTodas()
{
    Notificacao::where('usuario_id', Auth::id())->where('lida', false)->update(['lida' => true]);
    return response()->json(['status' => 'ok']);
}


}
