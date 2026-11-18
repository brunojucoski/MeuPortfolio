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
    public const ESTILO_CARD_CATEGORIA_LIVRO_EXPANSIVEL = 6;
    public const ESTILO_CARD_CATEGORIA_CAMADAS_3D = 7;
    public const ESTILO_CARD_CATEGORIA_AURA = 8;
    public const ESTILO_CARD_CATEGORIA_PARALELOGRAMO = 9;

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
            self::ESTILO_CARD_CATEGORIA_LIVRO_EXPANSIVEL => [
                'nome' => 'Livro expansível',
                'descricao' => 'Capa que se abre com uma página de fotos deslizante.',
            ],
            self::ESTILO_CARD_CATEGORIA_CAMADAS_3D => [
                'nome' => 'Camadas 3D',
                'descricao' => 'Card inclinado com sombras em camadas nas cores do perfil.',
            ],
            self::ESTILO_CARD_CATEGORIA_AURA => [
                'nome' => 'Aura luminosa',
                'descricao' => 'Moldura em degradê com brilho difuso e transição suave.',
            ],
            self::ESTILO_CARD_CATEGORIA_PARALELOGRAMO => [
                'nome' => 'Paralelogramo',
                'descricao' => 'Recorte inclinado com fotos, ícone e elevação ao passar o mouse.',
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
