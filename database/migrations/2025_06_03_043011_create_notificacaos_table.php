<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up()
{
    Schema::create('notificacoes', function (Blueprint $table) {
        $table->id();
        $table->foreignId('usuario_id')->constrained('usuarios')->onDelete('cascade');
        $table->foreignId('remetente_id')->nullable()->constrained('usuarios')->onDelete('set null');
        $table->text('mensagem');
        $table->foreignId('proposta_id')->constrained('proposta_contrato')->onDelete('cascade');
        $table->boolean('lida')->default(false);
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notificacoes');
    }
};
