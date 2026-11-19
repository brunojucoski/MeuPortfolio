<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tipo_usuario', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
        });

        // These IDs are part of the authorization contract, not optional demo data.
        DB::table('tipo_usuario')->insert([
            ['id' => 1, 'nome' => 'admin'],
            ['id' => 2, 'nome' => 'artista'],
            ['id' => 3, 'nome' => 'contratante'],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tipo_usuario');
    }
};
