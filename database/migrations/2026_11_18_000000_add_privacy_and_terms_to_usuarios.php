<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            // Existing coordinates are not evidence of consent to publish a location.
            $table->boolean('compartilhar_localizacao')->default(false);
            $table->timestamp('localizacao_autorizada_em')->nullable();
            $table->timestamp('termos_aceitos_em')->nullable();
            $table->string('termos_versao', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropColumn(['compartilhar_localizacao', 'localizacao_autorizada_em', 'termos_aceitos_em', 'termos_versao']);
        });
    }
};
