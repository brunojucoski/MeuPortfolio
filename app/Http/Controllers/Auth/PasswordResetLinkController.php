<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function __construct()
    {
        $this->middleware('throttle:6,1')->only('store');
    }

    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        if (strcasecmp($request->email, Usuario::EMAIL_VISITANTE_NAO_IDENTIFICADO) !== 0) {
            Password::sendResetLink($request->only('email'));
        }

        // Do not disclose whether this address belongs to an active account.
        return back()->with('status', 'Se houver uma conta ativa com esse e-mail, enviaremos um link para redefinir a senha.');
    }
}
