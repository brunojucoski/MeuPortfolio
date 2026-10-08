<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EnderecoController extends Controller
{
    public function buscar(Request $request)
    {
        $data = $request->validate(['q' => 'required|string|max:300']);
        return $this->consultar('search', ['q' => $data['q'], 'limit' => 1, 'countrycodes' => 'br']);
    }

    public function reverso(Request $request)
    {
        $data = $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lon' => 'required|numeric|between:-180,180',
        ]);
        return $this->consultar('reverse', [
            'lat' => number_format((float) $data['lat'], 6, '.', ''),
            'lon' => number_format((float) $data['lon'], 6, '.', ''),
        ]);
    }

    private function consultar(string $endpoint, array $params)
    {
        $base = rtrim(config('services.nominatim.url'), '/');
        $params += ['format' => 'jsonv2', 'addressdetails' => 1, 'accept-language' => 'pt-BR'];
        $key = 'endereco:'.hash('sha256', $base.$endpoint.json_encode($params));
        if (($cached = Cache::get($key)) !== null) return response()->json($cached);

        // Limite global de 1 consulta/s, inclusive entre usuarios: politica do Nominatim.
        $lock = Cache::lock('endereco:provider-lock', 20);
        if (! $lock->get()) {
            return response()->json(['message' => 'Aguarde para consultar o mapa novamente.'], 429)->header('Retry-After', '1');
        }
        try {
            if (microtime(true) - (float) Cache::get('endereco:last-request', 0) < 1) {
                return response()->json(['message' => 'Aguarde para consultar o mapa novamente.'], 429)->header('Retry-After', '1');
            }
            Cache::put('endereco:last-request', microtime(true), 60);
            $response = Http::acceptJson()->withUserAgent('MeuPortfolio/1.0 ('.config('app.url').')')
                ->connectTimeout(8)->timeout(15)->get($base.'/'.$endpoint, $params);
            if (! $response->successful() || ! is_array($response->json())) {
                Log::warning('Servico de geocodificacao indisponivel.', ['status' => $response->status()]);
                return response()->json(['message' => 'Consulta de localizacao indisponivel.'], 503);
            }
            $body = $response->json();
            Cache::put($key, $body, now()->addDay());
            return response()->json($body);
        } catch (\Illuminate\Http\Client\ConnectionException $exception) {
            Log::warning('Falha na conexao de geocodificacao.', [
                'reason' => Str::before($exception->getMessage(), ' for '),
            ]);
            return response()->json(['message' => 'Consulta de localizacao indisponivel.'], 503);
        } finally {
            $lock->release();
        }
    }
}
