<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoriaOrcamento extends Model
{
    protected $table = 'categorias_orcamento';

    protected $fillable = ['id_portfolio_artista', 'nome', 'ordem'];

    protected $casts = ['ordem' => 'integer'];

    public function portfolio()
    {
        return $this->belongsTo(PortfolioArtista::class, 'id_portfolio_artista');
    }

    public function perguntas()
    {
        return $this->hasMany(PerguntaPropostaContrato::class, 'id_categoria_orcamento')->orderBy('ordem')->orderBy('id');
    }

    public function propostas()
    {
        return $this->hasMany(PropostaContrato::class, 'id_categoria_orcamento');
    }
}
