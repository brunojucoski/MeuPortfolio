<?php

namespace App\Support;

use App\Models\Usuario;
use Illuminate\Http\Request;

class GoogleOAuth
{
    public const CONTEXT = 'google_oauth.context';
    public const PENDING = 'google_oauth.pending';
    public const TTL = 600;

    public static function configured(): bool
    {
        return (bool) config('services.google.enabled')
            && filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'));
    }

    public static function fresh(?array $data): bool
    {
        $created = $data['created_at'] ?? 0;
        return is_int($created) && $created <= now()->timestamp && $created > now()->timestamp - self::TTL;
    }

    public static function pending(Request $request): ?array
    {
        $identity = $request->session()->get(self::PENDING);
        if (!is_array($identity) || !self::fresh($identity)) {
            $request->session()->forget(self::PENDING);
            return null;
        }
        return $identity;
    }

    public static function artistOrigin($value): ?int
    {
        if (!is_string($value) && !is_int($value)) return null;
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $id !== false && Usuario::whereKey($id)->where('tipo_usuario', 2)->exists() ? $id : null;
    }

    public static function eligible(Usuario $user): bool
    {
        return !$user->trashed() && in_array((int) $user->tipo_usuario, [2, 3], true)
            && !$user->ehVisitanteNaoIdentificado();
    }

    public static function destination($origin): string
    {
        $id = self::artistOrigin($origin);
        return $id ? route('usuarios.perfilPublico', ['id' => $id]) : route('perfil');
    }
}
