<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PortfolioArtista extends Model
{
    use HasFactory, SoftDeletes;

    public const ESTILO_CARD_CATEGORIA_3D = 1;
    public const ESTILO_CARD_CATEGORIA_POLAROID = 2;
    public const ESTILO_CARD_CATEGORIA_REVEAL = 3;
    public const ESTILO_CARD_CATEGORIA_LIVRO = 4;
    public const ESTILO_CARD_CATEGORIA_PAINEL_3D = 5;

    protected $table = 'portfolio_artistas';

    protected $fillable = [
        'id_usuario',
        'nome_artistico',
        'descricao',
        'link_instagram',
        'link_behance',
        'link_tiktok',
        'link_github',
        'link_linkedin',
        'cor_primaria_portfolio',
        'cor_secundaria_portfolio',
        'estilo_card_categorias_portfolio',
    ];

    protected $casts = [
        'estilo_card_categorias_portfolio' => 'integer',
    ];

    public static function estilosCardsCategorias(): array
    {
        return [
            self::ESTILO_CARD_CATEGORIA_3D => [
                'nome' => '3D com fotos',
                'descricao' => 'Card inclinado com profundidade e hover na cor do perfil.',
            ],
            self::ESTILO_CARD_CATEGORIA_POLAROID => [
                'nome' => 'Pilha polaroid',
                'descricao' => 'Fotos sobrepostas com moldura clara e movimento suave.',
            ],
            self::ESTILO_CARD_CATEGORIA_REVEAL => [
                'nome' => 'Reveal',
                'descricao' => 'Capa com foto e painel branco revelado no hover.',
            ],
            self::ESTILO_CARD_CATEGORIA_LIVRO => [
                'nome' => 'Livro',
                'descricao' => 'Capa com fotos que se abre para revelar o álbum.',
            ],
            self::ESTILO_CARD_CATEGORIA_PAINEL_3D => [
                'nome' => 'Painel 3D',
                'descricao' => 'Fotos com painel que gira de baixo para cima.',
            ],
        ];
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario');
    }


    public function posts()
{
    return $this->hasMany(PostPortfolio::class, 'id_portfolio')->orderBy('created_at', 'desc');
}

    public function categoriasPostsPortfolio()
    {
        return $this->hasMany(CategoriaPostPortfolio::class, 'id_portfolio_artista')
            ->orderBy('ordem')
            ->orderBy('nome');
    }

    public function perguntasPropostaContrato()
    {
        return $this->hasMany(PerguntaPropostaContrato::class, 'id_portfolio_artista')
            ->orderBy('ordem')
            ->orderBy('id');
    }

    public function categoriasOrcamento()
    {
        return $this->hasMany(CategoriaOrcamento::class, 'id_portfolio_artista')->orderBy('ordem')->orderBy('id');
    }

   public function feedbacks() 
    {
        
        return $this->hasMany(FeedbackArtista::class, 'id_artista');
    }

public function feedbacksRecebidos()
{
    return $this->hasMany(\App\Models\FeedbackArtista::class, 'id_artista');
}
}
