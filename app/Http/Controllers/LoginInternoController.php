<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginInternoController extends Controller
{
    public function create()
    {
        return view('login_interno');
    }

    public function store(Request $request)
    {
        $dados = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (!Auth::attempt($dados + ['tipo_usuario' => 1])) {
            return back()->withInput($request->only('email'))->withErrors(['email' => 'Credenciais inválidas ou acesso não autorizado.']);
        }
        $request->session()->regenerate();
        return redirect()->route('categorias-artisticas.index');
    }
}
