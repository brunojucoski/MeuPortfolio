<?php

namespace App\Support;

use App\Models\Usuario;
use App\Notifications\CodigoCadastro;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class ConfirmacaoCadastro
{
    public const SESSAO = 'cadastro_em_confirmacao';
    private const REENVIO = 180;
    private const TENTATIVAS = 5;

    public function iniciar(Request $request, Usuario $usuario, ?int $origem): array
    {
        $anterior = $request->session()->get(self::SESSAO);
        if ($anterior) {
            Cache::forget('cadastro:codigo:'.$anterior['id']);
        }
        $pendente = [
            'id' => (string) Str::uuid(),
            'email' => $usuario->email,
            'perfil' => $usuario->tipo_usuario == 2 ? 'artista' : 'solicitante',
            'dados' => Crypt::encrypt($usuario->getAttributes()),
            'origem' => $origem,
            'expira' => now()->addMinutes(30)->timestamp,
        ];
        $request->session()->put(self::SESSAO, $pendente);

        return $pendente;
    }

    public function pendente(Request $request): ?array
    {
        $pendente = $request->session()->get(self::SESSAO);
        if ($pendente && $pendente['expira'] > now()->timestamp) {
            return $pendente;
        }
        $request->session()->forget(self::SESSAO);

        return null;
    }

    private function chaveEspera(array $pendente): string
    {
        return 'cadastro:reenvio:'.hash('sha256', Str::lower($pendente['email']));
    }

    public function espera(array $pendente): int
    {
        return max(0, (int) Cache::get($this->chaveEspera($pendente)) - now()->timestamp);
    }

    private function assinatura(string $id, string $codigo): string
    {
        return hash_hmac('sha256', $id.':'.$codigo, config('app.key'));
    }

    public function enviar(array $pendente, string $ip): ?string
    {
        if (app()->environment('production') && in_array(config('mail.default'), ['array', 'log'], true)) {
            Log::warning('O cadastro requer um transporte de e-mail ativo em produção.');
            return 'O envio de códigos está indisponível. Tente novamente mais tarde.';
        }
        $limite = 'cadastro:envios:'.$ip;
        if (RateLimiter::tooManyAttempts($limite, 20)) {
            return 'Limite de envios atingido. Tente novamente mais tarde.';
        }
        if (!Cache::add($this->chaveEspera($pendente), now()->timestamp + self::REENVIO, self::REENVIO)) {
            return 'Aguarde o intervalo de três minutos para solicitar um novo código.';
        }
        RateLimiter::hit($limite, 3600);
        $id = $pendente['id'];
        $validade = now()->addMinutes(CodigoCadastro::VALIDADE_MINUTOS);
        $key = 'cadastro:codigo:'.$id;
        $codigo = Cache::lock($key.':lock', 10)->block(3, function () use ($key, $id, $validade) {
            $anterior = Cache::get($key)['assinatura'] ?? null;
            do {
                $codigo = (string) random_int(100000, 999999);
                $assinatura = $this->assinatura($id, $codigo);
            } while ($assinatura === $anterior);
            Cache::put($key, [
                'assinatura' => $assinatura,
                'tentativas' => self::TENTATIVAS,
                'expira' => $validade->timestamp,
            ], $validade);
            return $codigo;
        });
        try {
            Notification::route('mail', $pendente['email'])->notify(new CodigoCadastro($codigo));
        } catch (\Symfony\Component\Mailer\Exception\TransportExceptionInterface $error) {
            Log::warning('Não foi possível enviar o código de cadastro pelo transporte de e-mail.', ['tipo' => $error::class]);
            return 'Não foi possível confirmar o envio do e-mail. Aguarde três minutos e solicite outro código.';
        }

        return null;
    }

    public function consumir(array $pendente, string $codigo): bool
    {
        $id = $pendente['id'];
        $key = 'cadastro:codigo:'.$id;
        return Cache::lock($key.':lock', 10)->block(3, function () use ($id, $key, $codigo) {
            $estado = Cache::get($key);
            if (!$estado || $estado['expira'] <= now()->timestamp) {
                return false;
            }
            $correto = hash_equals($estado['assinatura'], $this->assinatura($id, $codigo));
            if ($correto || --$estado['tentativas'] === 0) {
                Cache::forget($key);
            } else {
                Cache::put($key, $estado, $estado['expira'] - now()->timestamp);
            }
            return $correto;
        });
    }
}
