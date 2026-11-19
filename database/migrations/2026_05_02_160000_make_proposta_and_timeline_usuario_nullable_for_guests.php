<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proposta_contrato', function (Blueprint $table) {
            $table->unsignedBigInteger('id_usuario_avaliador')->nullable()->change();
        });
        Schema::table('timeline_proposta_contrato', function (Blueprint $table) {
            $table->unsignedBigInteger('id_usuario')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Refuse rollback rather than delete guest proposals and cascaded files.
        if (DB::table('proposta_contrato')->whereNull('id_usuario_avaliador')->exists()
            || DB::table('timeline_proposta_contrato')->whereNull('id_usuario')->exists()) {
            throw new LogicException('Nao e possivel reverter enquanto houver propostas ou mensagens de visitantes.');
        }

        Schema::table('timeline_proposta_contrato', function (Blueprint $table) {
            $table->unsignedBigInteger('id_usuario')->nullable(false)->change();
        });
        Schema::table('proposta_contrato', function (Blueprint $table) {
            $table->unsignedBigInteger('id_usuario_avaliador')->nullable(false)->change();
        });
    }
};
