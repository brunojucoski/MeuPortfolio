<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('proposta_contrato', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->unsignedBigInteger('id_artista');
            $table->foreign('id_artista')-> references('id')->on('portfolio_artistas')->onDelete('cascade');
            $table->unsignedBigInteger('id_usuario_avaliador');
            $table->foreign('id_usuario_avaliador')-> references('id')->on('usuarios')->onDelete('cascade');
            $table->string('titulo');
            $table->string('descricao');
            $table->dateTime('data'); 
            $table->softDeletes();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proposta_contrato');
    }
};
