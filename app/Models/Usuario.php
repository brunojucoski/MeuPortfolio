<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;



class Usuario extends Authenticatable
{
    use Notifiable, SoftDeletes, HasApiTokens , HasFactory;

    /** Conta interna usada em propostas enviadas sem login (não use para login manual). */
    public const EMAIL_VISITANTE_NAO_IDENTIFICADO = 'visitante-nao-identificado@meuportfolio.local';

    protected static ?int $visitanteNaoIdentificadoIdCache = null;

    protected $table = 'usuarios';

    protected $fillable = [
        'nome', 
        'documento', 
        'email', 
        'senha',
        'tipo_usuario', 
        'data_nasc', 
        'cep', 
        'bairro',
        'endereco', 
        'cidade',
        'latitude',
        'longitude',
        'telefone'
    ];

    protected $hidden = [
        'senha', 'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'data_nasc' => 'date',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    public function tipo()
    {
        return $this->belongsTo(TipoUsuario::class, 'tipo_usuario');
    }

    /**
     * ID do usuário interno "Não se identificou", ou null se a migração ainda não foi executada.
     */
    public static function idVisitanteNaoIdentificado(): ?int
    {
        if (self::$visitanteNaoIdentificadoIdCache !== null) {
            return self::$visitanteNaoIdentificadoIdCache;
        }

        self::$visitanteNaoIdentificadoIdCache = static::query()
            ->where('email', self::EMAIL_VISITANTE_NAO_IDENTIFICADO)
            ->value('id');

        return self::$visitanteNaoIdentificadoIdCache;
    }

    public function ehVisitanteNaoIdentificado(): bool
    {
        return $this->email === self::EMAIL_VISITANTE_NAO_IDENTIFICADO;
    }



    
    public function getAuthPassword()
    {
        return $this->senha;
    }

    public function getIdadeAttribute()
{
    return Carbon::parse($this->data_nasc)->age;
}

public function portfolioArtista()
{
    return $this->hasOne(PortfolioArtista::class, 'id_usuario');
}

public function portfolio()
{
    return $this->hasOne(\App\Models\PortfolioArtista::class, 'id_usuario');
}


public function categoriasArtisticas()
{
    return $this->belongsToMany(CategoriaArtistica::class, 'categorias_usuarios', 'id_usuario', 'id_categoria');
}


 public function todosFeedbacksRecebidosArtista()
    {
        return $this->hasManyThrough(
            FeedbackArtista::class,     
            PortfolioArtista::class,    
            'id_usuario',               
            'id_artista',               
            'id',                        
            'id'                       
        );
    }
    
   public function todosFeedbacksRecebidosContratante()
    {
       
        return $this->hasMany(FeedbackContratante::class, 'id_usuario');
    }

public function notificacoes()
{
    return $this->hasMany(Notificacao::class);
}
}
