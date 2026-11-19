<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

class tipo_usuario extends Seeder
{

    public function run(): void
    {
        DB::transaction(function () {
            foreach ([1 => 'admin', 2 => 'artista', 3 => 'contratante'] as $id => $nome) {
                DB::table('tipo_usuario')->insertOrIgnore(['id' => $id, 'nome' => $nome]);
                if (DB::table('tipo_usuario')->where('id', $id)->value('nome') !== $nome) {
                    throw new LogicException("O tipo de usuario {$id} deve corresponder a {$nome}; revise os dados antes de publicar.");
                }
            }

            // Fixed IDs do not advance PostgreSQL sequences.
            if (DB::getDriverName() === 'pgsql') {
                DB::statement("SELECT setval(pg_get_serial_sequence('tipo_usuario', 'id'), GREATEST((SELECT MAX(id) FROM tipo_usuario), (SELECT nextval(pg_get_serial_sequence('tipo_usuario', 'id')))), true)");
            }
        });
    }
}
