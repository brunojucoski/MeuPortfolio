<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracoes_sistema', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('cor_base', 7)->default('#6d2e2e');
            $table->string('sobre_titulo')->default('Sobre o MeuPortfólio');
            $table->text('sobre_texto');
            foreach (['login', 'cadastro_artista', 'cadastro_solicitante', 'sobre'] as $imagem) {
                $table->string('imagem_' . $imagem)->nullable();
            }
            $table->foreignId('atualizado_por')->nullable()->constrained('usuarios');
            $table->timestamps();
        });
        DB::table('configuracoes_sistema')->insert([
            'id' => 1, 'cor_base' => '#6d2e2e', 'sobre_titulo' => 'Sobre o MeuPortfólio',
            'sobre_texto' => 'O MeuPortfólio é uma plataforma de conexão entre artistas e solicitantes, que visa facilitar a busca e a contratação de serviços. A fim de dar visibilidade para profissionais do setor cultural. O projeto é sem fins lucrativos e é desenvolvido por @cajutatueiro para a comunidade ... Porém aceito investimentos hehe tenho mais ideias para implementar também inclusive.',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracoes_sistema');
    }
};
