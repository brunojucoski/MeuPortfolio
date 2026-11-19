<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
 public function up()
{
    Schema::table('feedbacks_contratantes', function (Blueprint $table) {
        $table->unsignedBigInteger('id_proposta')->after('id_usuario_avaliador');
        $table->foreign('id_proposta')->references('id')->on('proposta_contrato')->onDelete('cascade');
    });
}

    public function down(): void
    {
        Schema::table('feedbacks_contratantes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_proposta');
        });
    }
};
