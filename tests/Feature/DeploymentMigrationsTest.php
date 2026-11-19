<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

class DeploymentMigrationsTest extends TestCase
{
    use \Tests\Support\ConfirmaCadastro;

    private ?string $isolatedSchema = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Never inherit DB_URL or the application's actual database credentials.
        $connection = ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true];
        if (getenv('DEPLOYMENT_TEST_DB_DRIVER') === 'pgsql') {
            $this->isolatedSchema = 'deployment_qa_' . bin2hex(random_bytes(8));
            $connection = [
                'driver' => 'pgsql', 'host' => '127.0.0.1', 'port' => 55433,
                'database' => 'meuportfolio_migrations_qa', 'username' => 'migrations_qa',
                'password' => 'isolated-migrations-qa-only', 'charset' => 'utf8',
                'prefix' => '', 'search_path' => $this->isolatedSchema, 'sslmode' => 'disable',
            ];
        }
        config(['database.default' => 'deployment_migrations', 'database.connections.deployment_migrations' => $connection]);
        DB::purge('deployment_migrations');
        if ($this->isolatedSchema !== null) {
            DB::statement('CREATE SCHEMA "' . $this->isolatedSchema . '"');
        }

        $this->migrateAll();
    }

    protected function tearDown(): void
    {
        try {
            if ($this->isolatedSchema !== null) {
                DB::connection('deployment_migrations')->statement('DROP SCHEMA "' . $this->isolatedSchema . '" CASCADE');
            }
            DB::purge('deployment_migrations');
        } finally {
            parent::tearDown();
        }
    }

    private function migrateAll(): void
    {
        $this->artisan('migrate', [
            '--database' => 'deployment_migrations', '--path' => 'database/migrations', '--force' => true,
        ])->assertExitCode(0);
    }

    public function test_artist_directory_and_map_on_a_new_deployment(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::connection()->getPdo()->setAttribute(\PDO::ATTR_EMULATE_PREPARES, true);
        } else {
            // The XAMPP SQLite build lacks the math functions used by the map.
            foreach (['sin' => 'sin', 'cos' => 'cos', 'radians' => 'deg2rad', 'power' => 'pow'] as $sql => $php) {
                DB::connection()->getPdo()->sqliteCreateFunction($sql, $php, $sql === 'power' ? 2 : 1);
            }
        }

        $this->withoutExceptionHandling();
        $this->get(route('usuarios.publico'))->assertOk()->assertViewHas('centroMapa', [-14.235, -51.9253]);
        $this->getJson(route('usuarios.mapa', ['latitude' => -14.235, 'longitude' => -51.9253]))
            ->assertOk()->assertJsonCount(0, 'artistas');
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['artista'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['solicitante'])]
    public function test_new_account_opens_profile_after_confirmation(string $tipo): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::connection()->getPdo()->setAttribute(\PDO::ATTR_EMULATE_PREPARES, true);
        }
        $this->withoutExceptionHandling();
        $this->cadastrarEConfirmar(route('usuarios.cadastrar'), [
            'nome' => 'Perfil de teste', 'email' => 'perfil@example.test',
            'documento' => '12345678901', 'data_nasc' => '1990-01-01',
            'senha' => 'password', 'senha_confirmation' => 'password',
            'perfil_cadastro' => $tipo, 'aceite_termos' => 1,
        ])->assertRedirect(route('perfil'));
        $this->get(route('perfil'))->assertOk()->assertSee('Perfil de teste');
        $this->getJson('/notificacoes')->assertOk()->assertExactJson([]);
    }

    public function test_complete_history_and_production_seeds_are_repeatable(): void
    {
        $this->assertSame(count(glob(database_path('migrations/*.php'))), DB::table('migrations')->count());
        $this->assertTrue(Schema::hasColumns('usuarios', [
            'latitude', 'longitude', 'compartilhar_localizacao', 'termos_aceitos_em', 'google_id',
        ]));
        $this->assertFalse(Schema::hasColumn('usuarios', 'sexo_usuario'));
        $this->assertFalse(Schema::hasTable('sexo_usuario'));
        $this->assertTrue(Schema::hasColumns('categorias_artisticas', ['created_at', 'updated_at']));
        $this->assertTrue(Schema::hasColumns('proposta_contrato', ['id_categoria_orcamento', 'categoria_orcamento_nome']));
        $this->assertTrue(Schema::hasColumns('configuracoes_sistema', ['imagem_logo', 'termos_responsavel', 'termos_contato_email']));
        foreach (['sessions', 'cache', 'cache_locks'] as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }

        $guest = DB::table('usuarios')->where('email', 'visitante-nao-identificado@meuportfolio.local')->first();
        $this->assertNotNull($guest);
        $this->assertSame(3, (int) $guest->tipo_usuario);
        $this->assertFalse((bool) $guest->compartilhar_localizacao);
        $this->assertNull($guest->termos_aceitos_em);

        DB::table('configuracoes_sistema')->where('id', 1)->update(['cor_base' => '#123456']);
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);
        $this->migrateAll();
        $this->assertSame([1 => 'admin', 2 => 'artista', 3 => 'contratante'], DB::table('tipo_usuario')->orderBy('id')->pluck('nome', 'id')->all());
        $this->assertSame(1, DB::table('usuarios')->count());
        $this->assertSame($guest->senha, DB::table('usuarios')->where('id', $guest->id)->value('senha'));
        $this->assertSame('#123456', DB::table('configuracoes_sistema')->where('id', 1)->value('cor_base'));
        $this->assertGreaterThan(3, DB::table('tipo_usuario')->insertGetId(['nome' => 'future-role']));
    }

    public function test_guest_nullable_relations_and_long_notifications_keep_foreign_keys(): void
    {
        $guestId = DB::table('usuarios')->value('id');
        $portfolioId = DB::table('portfolio_artistas')->insertGetId(['id_usuario' => $guestId]);
        $proposalId = DB::table('proposta_contrato')->insertGetId(['id_artista' => $portfolioId, 'id_usuario_avaliador' => null]);
        $timelineId = DB::table('timeline_proposta_contrato')->insertGetId([
            'id_proposta' => $proposalId, 'id_usuario' => null, 'tipo' => 'mensagem', 'texto' => 'Guest message',
        ]);
        $message = str_repeat('Notificacao de teste. ', 100);
        $notificationId = DB::table('notificacoes')->insertGetId([
            'usuario_id' => $guestId, 'proposta_id' => $proposalId, 'mensagem' => $message,
        ]);
        $this->assertSame($message, DB::table('notificacoes')->where('id', $notificationId)->value('mensagem'));

        $migration = require database_path('migrations/2026_05_02_160000_make_proposta_and_timeline_usuario_nullable_for_guests.php');
        try {
            $migration->down();
            $this->fail('Rollback must not delete guest data.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('visitantes', $exception->getMessage());
        }
        $this->assertTrue(DB::table('timeline_proposta_contrato')->where('id', $timelineId)->exists());

        try {
            DB::table('timeline_proposta_contrato')->insert([
                'id_proposta' => $proposalId, 'id_usuario' => 999999, 'tipo' => 'mensagem',
            ]);
            $this->fail('Orphan users must be rejected by the foreign key.');
        } catch (QueryException $exception) {
            $this->assertNotEmpty($exception->getCode());
        }

        DB::table('proposta_contrato')->where('id', $proposalId)->delete();
        $this->assertSame(0, DB::table('timeline_proposta_contrato')->count());
        $this->assertSame(0, DB::table('notificacoes')->count());
    }

    public function test_seed_repairs_missing_required_roles_without_renumbering_users(): void
    {
        DB::table('tipo_usuario')->whereIn('id', [1, 2])->delete();
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);
        $this->assertSame(3, DB::table('tipo_usuario')->count());
        $this->assertSame(3, (int) DB::table('usuarios')->value('tipo_usuario'));
    }

    public function test_nullable_upgrade_preserves_existing_data_and_foreign_keys(): void
    {
        $migration = require database_path('migrations/2026_05_02_160000_make_proposta_and_timeline_usuario_nullable_for_guests.php');
        $migration->down();
        $userId = DB::table('usuarios')->value('id');
        $portfolioId = DB::table('portfolio_artistas')->insertGetId(['id_usuario' => $userId]);
        $proposalId = DB::table('proposta_contrato')->insertGetId(['id_artista' => $portfolioId, 'id_usuario_avaliador' => $userId]);
        $timelineId = DB::table('timeline_proposta_contrato')->insertGetId([
            'id_proposta' => $proposalId, 'id_usuario' => $userId, 'tipo' => 'mensagem', 'texto' => 'Existing message',
        ]);
        $migration->up();
        $this->assertSame($userId, DB::table('proposta_contrato')->where('id', $proposalId)->value('id_usuario_avaliador'));
        $this->assertSame('Existing message', DB::table('timeline_proposta_contrato')->where('id', $timelineId)->value('texto'));
        DB::table('timeline_proposta_contrato')->where('id', $timelineId)->update(['id_usuario' => null]);
        DB::table('proposta_contrato')->where('id', $proposalId)->update(['id_usuario_avaliador' => null]);
        $this->assertNull(DB::table('timeline_proposta_contrato')->where('id', $timelineId)->value('id_usuario'));
        DB::table('usuarios')->where('id', $userId)->delete();
        $this->assertSame(0, DB::table('proposta_contrato')->count());
        $this->assertSame(0, DB::table('timeline_proposta_contrato')->count());
    }

    public function test_seed_refuses_role_id_conflicts_instead_of_changing_authorization(): void
    {
        DB::table('tipo_usuario')->where('id', 1)->update(['nome' => 'artista']);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('tipo de usuario 1');
        $this->seed(DatabaseSeeder::class);
    }

    public function test_legacy_guest_migration_bootstraps_its_missing_role_without_seeding_accounts(): void
    {
        DB::table('usuarios')->delete();
        DB::table('tipo_usuario')->where('id', 3)->delete();
        $migration = require database_path('migrations/2026_05_02_170000_create_usuario_visitante_nao_identificado.php');
        $migration->up();
        $migration->up();
        $this->assertSame('contratante', DB::table('tipo_usuario')->where('id', 3)->value('nome'));
        $this->assertSame(1, DB::table('usuarios')->count());
        $this->assertSame(3, (int) DB::table('usuarios')->value('tipo_usuario'));
        $this->assertFalse(DB::table('usuarios')->where('tipo_usuario', 1)->exists());
    }

    public function test_full_history_can_be_reversed_and_reapplied_in_an_empty_isolated_database(): void
    {
        $this->artisan('migrate:reset', ['--database' => 'deployment_migrations', '--force' => true])->assertExitCode(0);
        $this->assertFalse(Schema::hasTable('usuarios'));
        $this->assertFalse(Schema::hasTable('proposta_contrato'));
        $this->assertFalse(Schema::hasTable('notificacoes'));
        $this->assertFalse(Schema::hasTable('password_reset_tokens'));
        $this->assertFalse(Schema::hasTable('tipo_usuario'));
        $this->assertSame(0, DB::table('migrations')->count());
        $this->migrateAll();
        $this->seed(DatabaseSeeder::class);
        $this->assertSame(3, DB::table('tipo_usuario')->count());
    }
}
