<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        $email = 'visitante-nao-identificado@meuportfolio.local';

        if (DB::table('usuarios')->where('email', $email)->exists()) {
            return;
        }

        $tipoContratante = DB::table('tipo_usuario')->where('nome', 'contratante')->value('id');
        if (! $tipoContratante) {
            // Also support older databases whose role migration ran without seeds.
            DB::table('tipo_usuario')->insertOrIgnore(['id' => 3, 'nome' => 'contratante']);
            if (DB::table('tipo_usuario')->where('id', 3)->value('nome') !== 'contratante') {
                throw new LogicException('O tipo de usuario 3 deve corresponder a contratante.');
            }
            $tipoContratante = 3;
        }

        DB::table('usuarios')->insert([
            'nome' => 'Não se identificou',
            'documento' => 'SISTEMA-NAO-IDENTIFICADO',
            'email' => $email,
            'senha' => Hash::make(bin2hex(random_bytes(32))),
            'tipo_usuario' => $tipoContratante,
            'data_nasc' => '2000-01-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $email = 'visitante-nao-identificado@meuportfolio.local';
        $id = DB::table('usuarios')->where('email', $email)->value('id');
        if ($id && DB::table('proposta_contrato')->where('id_usuario_avaliador', $id)->exists()) {
            return;
        }
        if ($id) {
            DB::table('usuarios')->where('id', $id)->delete();
        }
    }
};
