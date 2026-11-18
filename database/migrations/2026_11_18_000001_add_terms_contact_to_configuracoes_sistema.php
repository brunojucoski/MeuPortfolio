<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracoes_sistema', function (Blueprint $table) {
            $table->string('termos_responsavel', 200)->nullable();
            $table->string('termos_contato_email', 254)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('configuracoes_sistema', function (Blueprint $table) {
            $table->dropColumn(['termos_responsavel', 'termos_contato_email']);
        });
    }
};
