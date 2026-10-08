<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfiguracaoSistema extends Model
{
    protected $table = 'configuracoes_sistema';
    public $incrementing = false;
    protected $fillable = [
        'cor_base', 'sobre_titulo', 'sobre_texto', 'imagem_login', 'imagem_logo',
        'imagem_cadastro_artista', 'imagem_cadastro_solicitante', 'imagem_sobre', 'atualizado_por',
    ];
}
