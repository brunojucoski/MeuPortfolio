<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use App\Support\GoogleOAuth;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $response = $next($request);
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->set('Referrer-Policy', 'no-referrer');
            return $response;
        });
    }

    public function redirect(Request $request)
    {
        $data = $request->validate([
            'mode' => 'required|in:login,register',
            'perfil_cadastro' => 'required_if:mode,register|in:artista,solicitante',
        ]);
        return $this->start($request, $data['mode'], $data['perfil_cadastro'] ?? 'artista');
    }

    public function link(Request $request)
    {
        abort_unless(GoogleOAuth::eligible($request->user()), 403);
        if ($request->user()->google_id) {
            return redirect()->route('homepage')->with('success', 'Sua conta Google já está vinculada.');
        }
        return $this->start($request, 'link', 'artista');
    }

    private function start(Request $request, string $mode, string $profile)
    {
        if (!GoogleOAuth::configured()) {
            return $this->failure('O acesso com Google ainda não está configurado.', $mode);
        }
        $request->session()->forget([GoogleOAuth::CONTEXT, GoogleOAuth::PENDING, 'state']);
        $request->session()->put(GoogleOAuth::CONTEXT, [
            'mode' => $mode, 'profile' => $profile,
            'origin' => GoogleOAuth::artistOrigin($request->input('orcamento_artista')),
            'user_id' => $mode === 'link' ? $request->user()->id : null,
            'created_at' => now()->timestamp,
        ]);
        try {
            return Socialite::driver('google')->scopes(['openid', 'profile', 'email'])
                ->with(['prompt' => 'select_account'])->redirect();
        } catch (\Exception $exception) {
            $request->session()->forget([GoogleOAuth::CONTEXT, 'state']);
            return $this->failure('Não foi possível iniciar o acesso com Google. Tente novamente.', $mode);
        }
    }

    public function callback(Request $request)
    {
        $context = $request->session()->pull(GoogleOAuth::CONTEXT);
        $mode = is_array($context) ? ($context['mode'] ?? 'login') : 'login';
        $state = $request->session()->get('state');
        if (!is_array($context) || !GoogleOAuth::fresh($context) || !is_string($state)
            || !is_string($request->query('state')) || !hash_equals($state, $request->query('state'))) {
            $request->session()->forget('state');
            return $this->failure('A solicitação expirou ou é inválida. Inicie o acesso com Google novamente.', $mode);
        }
        if (!GoogleOAuth::configured() || $request->has('error')) {
            $request->session()->forget('state');
            return $this->failure('O acesso com Google foi cancelado ou não está disponível.', $mode);
        }
        if (!is_string($request->query('code')) || $request->query('code') === '') {
            $request->session()->forget('state');
            return $this->failure('O Google não retornou uma autorização válida. Tente novamente.', $mode);
        }
        if (($mode === 'link' && (!$request->user() || $request->user()->id !== $context['user_id']))
            || ($mode !== 'link' && Auth::check())) {
            $request->session()->forget('state');
            return $this->failure('A sessão mudou. Inicie o acesso com Google novamente.', $mode);
        }
        try {
            $google = Socialite::driver('google')->user();
        } catch (\Exception $exception) {
            // Provider exceptions can contain tokens or secrets; do not log their payload.
            return $this->failure('Não foi possível confirmar sua conta Google. Tente novamente.', $mode);
        } finally {
            $request->session()->forget('state');
        }
        $id = $google->getId();
        $email = $google->getEmail();
        if (!is_string($id) || $id === '' || strlen($id) > 255
            || !is_string($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255
            || ($google->user['email_verified'] ?? false) !== true) {
            return $this->failure('Use uma conta Google com e-mail verificado.', $mode);
        }
        $email = mb_strtolower($email);
        $linked = Usuario::withTrashed()->where('google_id', $id)->first();
        if ($mode === 'link') {
            return $this->completeLink($request, $id, $email, $linked);
        }
        if ($linked) {
            if (!GoogleOAuth::eligible($linked)) {
                return $this->failure('Esta conta não pode acessar a plataforma pelo Google.');
            }
            Auth::login($linked);
            $request->session()->forget(GoogleOAuth::PENDING);
            $request->session()->regenerate();
            return redirect(GoogleOAuth::destination($context['origin']));
        }
        if (Usuario::withTrashed()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
            return $this->failure('Este e-mail já está cadastrado. Entre com sua senha e use Vincular Google em Editar perfil.');
        }
        $request->session()->put(GoogleOAuth::PENDING, [
            'id' => $id, 'email' => $email, 'name' => mb_substr((string) $google->getName(), 0, 200),
            'profile' => $context['profile'], 'origin' => $context['origin'], 'created_at' => now()->timestamp,
        ]);
        $request->session()->regenerate();
        return redirect()->route('google.complete');
    }

    private function completeLink(Request $request, string $id, string $email, ?Usuario $linked)
    {
        try {
            $ok = DB::transaction(function () use ($request, $id, $email, $linked) {
                $user = Usuario::whereKey($request->user()->id)->lockForUpdate()->first();
                if (!$user || !GoogleOAuth::eligible($user) || $user->google_id
                    || mb_strtolower($user->email) !== $email || $linked) return false;
                $user->google_id = $id;
                $user->email_verified_at = now();
                $user->save();
                return true;
            });
        } catch (QueryException $exception) {
            $ok = false;
        }
        if (!$ok) return $this->failure('Não foi possível vincular. Use a conta Google com o mesmo e-mail do seu perfil, ainda não vinculada.', 'link');
        $request->session()->regenerate();
        return redirect()->route('homepage')->with('success', 'Conta Google vinculada. Você já pode usá-la para entrar.');
    }

    private function failure(string $message, string $mode = 'login')
    {
        $route = Auth::check() ? 'homepage' : ($mode === 'register' ? 'usuarios.cadastro' : 'login');
        return redirect()->route($route)->withErrors(['google' => $message]);
    }
}
