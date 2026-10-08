<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PropostaContrato extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'proposta_contrato';

    protected $fillable = [
        'id_artista',
        'id_categoria_orcamento',
        'categoria_orcamento_nome',
        'id_usuario_avaliador',
        'status',
        'motivo',
    ];

    protected $dates = ['deleted_at'];

    public function artista()
    {
        return $this->belongsTo(PortfolioArtista::class, 'id_artista');
    }

    public function categoriaOrcamento()
    {
        return $this->belongsTo(CategoriaOrcamento::class, 'id_categoria_orcamento');
    }

    public function usuarioAvaliador()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_avaliador');
    }

    
    // Feedback dado ao artista (por contratante)
public function feedbackArtista()
{
    return $this->hasOne(FeedbackArtista::class, 'id_usuario_avaliador', 'id_usuario_avaliador')
                ->whereColumn('id_artista', 'id_artista');
}

// Feedback dado ao contratante (por artista)
    public function feedbackContratante()
{
    return $this->hasOne(FeedbackContratante::class, 'id_proposta');
}

    /** Feedback do contratante sobre o artista (uma proposta → um registro). */
    public function feedbackArtistaNaProposta()
    {
        return $this->hasOne(FeedbackArtista::class, 'id_proposta');
    }

    public function respostasPergunta()
    {
        return $this->hasMany(RespostaPropostaPergunta::class, 'id_proposta');
    }

    public function timeline()
    {
        return $this->hasMany(TimelinePropostaContrato::class, 'id_proposta')->orderBy('created_at')->orderBy('id');
    }

    /**
     * Proposta feita por visitante sem cadastro (usuário interno "Não se identificou").
     */
    public function ehPropostaSemIdentificacao(): bool
    {
        $anonId = Usuario::idVisitanteNaoIdentificado();

        return $anonId !== null && (int) $this->id_usuario_avaliador === (int) $anonId;
    }

}
