<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('usuarios', 'sexo_usuario')) {
            Schema::table('usuarios', function (Blueprint $table) {
                if (DB::getDriverName() !== 'sqlite') {
                    $table->dropForeign(['sexo_usuario']);
                }
                $table->dropColumn('sexo_usuario');
            });
        }

        Schema::dropIfExists('sexo_usuario');
    }

    public function down(): void
    {
        Schema::create('sexo_usuario', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
        });
        DB::table('sexo_usuario')->insert([
            ['id' => 1, 'nome' => 'Masculino'],
            ['id' => 2, 'nome' => 'Feminino'],
            ['id' => 3, 'nome' => 'Nao informar'],
        ]);

        // A reversao restaura a estrutura, mas nao os dados removidos.
        Schema::table('usuarios', function (Blueprint $table) {
            $table->foreignId('sexo_usuario')->nullable()->constrained('sexo_usuario')->cascadeOnDelete();
        });
    }
};
