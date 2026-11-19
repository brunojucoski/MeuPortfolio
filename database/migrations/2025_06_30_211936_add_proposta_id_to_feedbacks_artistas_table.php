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
        Schema::table('feedbacks_artistas', function (Blueprint $table) {
            // Adiciona a coluna id_proposta
            $table->unsignedBigInteger('id_proposta')->after('id_artista')->nullable(); // Ou make it not nullable if feedbacks must always be tied to a proposal

            // Adiciona a chave estrangeira
            $table->foreign('id_proposta')
                  ->references('id')->on('proposta_contrato')
                  ->onDelete('cascade'); // Define o comportamento CASCADE ao deletar a proposta
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('feedbacks_artistas', function (Blueprint $table) {
            // Remove a chave estrangeira primeiro
            $table->dropConstrainedForeignId('id_proposta');
        });
    }
};
