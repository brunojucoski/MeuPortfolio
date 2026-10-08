<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PerguntaPropostaContrato extends Model
{
    protected $table = 'perguntas_proposta_contrato';

    protected $fillable = [
        'id_portfolio_artista',
        'id_categoria_orcamento',
        'tipo',
        'titulo',
        'opcoes_json',
        'ordem',
    ];

    protected $casts = [
        'opcoes_json' => 'array',
        'ordem' => 'integer',
    ];

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(PortfolioArtista::class, 'id_portfolio_artista');
    }

    public function respostas(): HasMany
    {
        return $this->hasMany(RespostaPropostaPergunta::class, 'id_pergunta');
    }

    public function categoriaOrcamento(): BelongsTo
    {
        return $this->belongsTo(CategoriaOrcamento::class, 'id_categoria_orcamento');
    }

    public function opcoesList(): array
    {
        return is_array($this->opcoes_json) ? $this->opcoes_json : [];
    }
}
