<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CategoriaPostPortfolio extends Model
{
    protected $table = 'categorias_posts_portfolio';

    protected $fillable = [
        'id_portfolio_artista',
        'nome',
        'descricao',
        'ordem',
        'icone',
    ];

    protected $casts = [
        'ordem' => 'integer',
    ];

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(PortfolioArtista::class, 'id_portfolio_artista');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(PostPortfolio::class, 'id_categoria_post_portfolio')
            ->orderByDesc('created_at');
    }

    /** Post mais recente (capa do card de categoria no perfil público). */
    public function coverPost(): HasOne
    {
        return $this->hasOne(PostPortfolio::class, 'id_categoria_post_portfolio')
            ->latestOfMany();
    }
}
