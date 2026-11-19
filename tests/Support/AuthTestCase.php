<?php

namespace Tests\Support;

use App\Models\Usuario;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

abstract class AuthTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'cache.default' => 'array', 'session.driver' => 'array', 'mail.default' => 'array',
        ]);
        DB::purge('sqlite');

        Schema::create('usuarios', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('documento')->unique();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('senha');
            $table->integer('tipo_usuario');
            $table->date('data_nasc');
            foreach (['telefone', 'cep', 'cidade', 'bairro', 'endereco', 'foto_perfil', 'google_id', 'termos_versao'] as $column) {
                $table->string($column)->nullable();
            }
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->boolean('compartilhar_localizacao')->default(false);
            $table->timestamp('localizacao_autorizada_em')->nullable();
            $table->timestamp('termos_aceitos_em')->nullable();
            $table->rememberToken();
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id(); $table->morphs('tokenable'); $table->string('name');
            $table->string('token', 64)->unique(); $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable(); $table->timestamp('expires_at')->nullable(); $table->timestamps();
        });
        Schema::create('notificacoes', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('usuario_id'); $table->boolean('lida')->default(false);
        });
    }

    protected function usuario(array $attributes = []): Usuario
    {
        $number = Usuario::withTrashed()->count() + 1;
        return Usuario::forceCreate(array_merge([
            'nome' => 'Pessoa '.$number, 'email' => 'pessoa'.$number.'@example.test',
            'documento' => str_pad((string) $number, 11, '0', STR_PAD_LEFT),
            'senha' => Hash::make('password'), 'data_nasc' => '1990-01-01',
            'tipo_usuario' => 3, 'email_verified_at' => now(),
        ], $attributes));
    }
}
