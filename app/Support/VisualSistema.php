<?php

namespace App\Support;

use App\Models\ConfiguracaoSistema;
use Illuminate\Support\Facades\Schema;

class VisualSistema
{
    private ?ConfiguracaoSistema $configuracao = null;
    private bool $carregada = false;

    public function configuracao(): ?ConfiguracaoSistema
    {
        if (!$this->carregada) {
            $this->carregada = true;
            if (Schema::hasTable('configuracoes_sistema')) {
                $this->configuracao = ConfiguracaoSistema::find(1);
            }
        }
        return $this->configuracao;
    }

    public function corBase(): string
    {
        $cor = $this->configuracao()?->cor_base;
        return preg_match('/^#[0-9a-fA-F]{6}$/', (string) $cor) ? $cor : config('visual-sistema.cor_base');
    }

    public function corSecundaria(): string
    {
        $cor = $this->corBase();
        if (strtolower($cor) === config('visual-sistema.cor_base')) return config('visual-sistema.cor_secundaria');
        $rgb = sscanf($cor, '#%02x%02x%02x');
        return sprintf('#%02x%02x%02x', ...array_map(fn ($canal) => (int) round($canal + (255 - $canal) * .18), $rgb));
    }

    public function imagemUrl(string $local): ?string
    {
        $caminho = $this->configuracao()?->{'imagem_' . $local};
        $padrao = config('visual-sistema.imagens.' . $local . '.padrao');
        return $caminho ? asset('storage/' . $caminho) : ($padrao ? asset($padrao) : null);
    }

    public function sobreTitulo(): string
    {
        return $this->configuracao()?->sobre_titulo ?? config('visual-sistema.sobre_titulo');
    }

    public function sobreTexto(): string
    {
        return $this->configuracao()?->sobre_texto ?? config('visual-sistema.sobre_texto');
    }
}
