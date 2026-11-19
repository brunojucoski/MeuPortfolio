<?php

namespace Tests\Feature;

use App\Http\Controllers\PropostaContratoController;
use App\Models\CategoriaOrcamento;
use App\Models\PerguntaPropostaContrato;
use App\Models\PortfolioArtista;
use App\Models\PropostaContrato;
use App\Models\RespostaPropostaPergunta;
use App\Models\Usuario;
use App\Support\MediaStorage;
use App\Support\PortfolioPdf;
use App\Support\StorageDeploymentCheck;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StorageDeploymentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'uploads.request_max_bytes' => 0, 'uploads.vercel' => false]);
        DB::purge('sqlite');
        foreach (['public', 'private', 'legacy_public'] as $disk) Storage::fake($disk);
        Schema::create('usuarios', function (Blueprint $t) {
            $t->id(); $t->string('nome'); $t->string('email')->nullable();
            $t->string('telefone')->nullable(); $t->integer('tipo_usuario'); $t->timestamps(); $t->softDeletes();
        });
        Schema::create('portfolio_artistas', function (Blueprint $t) {
            $t->id(); $t->foreignId('id_usuario'); $t->timestamps(); $t->softDeletes();
        });
        Schema::create('proposta_contrato', function (Blueprint $t) {
            $t->id(); $t->foreignId('id_artista'); $t->foreignId('id_usuario_avaliador');
            $t->string('status')->nullable(); $t->timestamps(); $t->softDeletes();
        });
        Schema::create('posts_portfolio', function (Blueprint $t) {
            $t->id(); $t->foreignId('id_portfolio'); $t->unsignedBigInteger('id_categoria_post_portfolio')->nullable();
            $t->string('nome'); $t->text('descricao'); $t->timestamps(); $t->softDeletes();
        });
        Schema::create('posts_imgs', function (Blueprint $t) {
            $t->id(); $t->foreignId('post_id'); $t->string('caminho_imagem'); $t->timestamps();
        });
        foreach (['2026_05_02_120000_create_perguntas_proposta_contrato_table',
            '2026_05_02_120001_create_respostas_proposta_pergunta_table',
            '2026_05_02_140000_create_timeline_proposta_contrato_table',
            '2025_06_03_043011_create_notificacaos_table',
            '2026_10_06_000000_create_categorias_orcamento'] as $migration) {
            (require database_path('migrations/'.$migration.'.php'))->up();
        }
        if (!Route::has('propostas.anexos.download')) {
            Route::get('/__storage/anexos/{resposta}', [PropostaContratoController::class, 'downloadAnexo'])
                ->middleware(['web', 'auth'])->name('propostas.anexos.download');
            Route::getRoutes()->refreshNameLookups();
        }
    }

    private function user(int $type): Usuario
    {
        return Usuario::create(['nome' => 'User', 'tipo_usuario' => $type]);
    }

    private function attachment(?string $path = null): array
    {
        $artist = $this->user(2);
        $client = $this->user(3);
        $portfolio = PortfolioArtista::create(['id_usuario' => $artist->id]);
        $proposal = PropostaContrato::create(['id_artista' => $portfolio->id, 'id_usuario_avaliador' => $client->id]);
        $question = PerguntaPropostaContrato::create(['id_portfolio_artista' => $portfolio->id, 'tipo' => 'anexo', 'titulo' => 'Reference']);
        $path ??= MediaStorage::PRIVATE_ATTACHMENTS.'file.txt';
        $answer = RespostaPropostaPergunta::create(['id_proposta' => $proposal->id, 'id_pergunta' => $question->id, 'caminho_anexo' => $path]);
        Storage::disk('private')->put($path, 'private content');
        return [$artist, $client, $answer];
    }

    public function test_public_urls_use_the_disk_and_reject_private_or_unsafe_paths(): void
    {
        Storage::disk('public')->buildTemporaryUrlsUsing(fn () => 'unused');
        $disk = Storage::build(['driver' => 'local', 'root' => storage_path('framework/testing/disks/public'), 'url' => 'https://cdn.example/public']);
        Storage::set('public', $disk);
        $this->assertSame('https://cdn.example/public/posts/photo.jpg', MediaStorage::publicUrl('posts/photo.jpg'));
        $this->assertSame('https://cdn.example/public/exemplo.jpg', MediaStorage::publicUrl('exemplo.jpg'));
        $this->assertSame('https://cdn.example/public/perfis/foto.png', MediaStorage::publicUrl('perfis/foto.png'));
        foreach (['../secret', '/secret', 'https://example.org/a.jpg', 'a/../b', 'a\\b', 'propostas_anexos/a.pdf', MediaStorage::PRIVATE_ATTACHMENTS.'a.pdf', 'a%2fb', 'a\'b'] as $path) {
            $this->assertNull(MediaStorage::publicUrl($path));
        }
    }

    public function test_pdf_reads_bounded_disk_bytes_without_local_path_assumptions(): void
    {
        $file = UploadedFile::fake()->image('photo.png', 12, 10);
        Storage::disk('public')->put('posts/photo.png', file_get_contents($file->path()));
        config(['filesystems.disks.public.driver' => 's3']);
        $image = (new \ReflectionMethod(PortfolioPdf::class, 'image'))->invoke(new PortfolioPdf(), 'posts/photo.png');
        $this->assertSame(12, $image['width']);
        $this->assertStringStartsWith('data:image/jpeg;base64,', $image['src']);
        $this->assertNull(MediaStorage::publicImageBytes('posts/photo.png', 4));
        $this->assertNull(MediaStorage::publicImageBytes('https://example.org/photo.png'));
        $this->assertNull(MediaStorage::publicImageBytes('propostas_anexos/photo.png'));
    }

    public function test_real_s3_adapter_serializes_private_upload_without_acl_or_network(): void
    {
        $handler = new \Aws\MockHandler([new \Aws\Result(['ETag' => 'test'])]);
        $config = ['driver' => 's3', 'root' => '', 'key' => 'test-key', 'secret' => 'test-secret',
            'region' => 'us-east-1', 'bucket' => 'private-media', 'endpoint' => 'https://storage.example.test/storage/v1/s3',
            'use_path_style_endpoint' => true, 'handler' => $handler, 'request_checksum_calculation' => 'when_required',
            'response_checksum_validation' => 'when_required', 'throw' => true];
        config(['filesystems.disks.private' => $config]);
        Storage::set('private', Storage::build($config));
        $path = MediaStorage::store(UploadedFile::fake()->createWithContent('file.txt', 'bytes'), rtrim(MediaStorage::PRIVATE_ATTACHMENTS, '/'), 'private');
        $this->assertStringStartsWith(MediaStorage::PRIVATE_ATTACHMENTS, $path);
        $request = $handler->getLastRequest();
        $this->assertSame('PUT', $request->getMethod());
        $this->assertStringContainsString('/private-media/'.MediaStorage::PRIVATE_ATTACHMENTS, $request->getUri()->getPath());
        $this->assertFalse($request->hasHeader('x-amz-acl'));
        $this->assertSame('attachment', $request->getHeaderLine('Content-Disposition'));
        $this->assertSame('application/octet-stream', $request->getHeaderLine('Content-Type'));
        $this->assertSame('private, no-store', $request->getHeaderLine('Cache-Control'));
    }

    public function test_stream_read_limit_is_enforced_even_if_object_metadata_is_stale(): void
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, str_repeat('x', 20));
        rewind($stream);
        $disk = \Mockery::mock(\Illuminate\Filesystem\FilesystemAdapter::class);
        $disk->shouldReceive('exists')->once()->with('posts/a.png')->andReturnTrue();
        $disk->shouldReceive('size')->once()->with('posts/a.png')->andReturn(4);
        $disk->shouldReceive('readStream')->once()->with('posts/a.png')->andReturn($stream);
        Storage::set('public', $disk);
        config(['filesystems.disks.public.driver' => 's3']);
        $this->assertNull(MediaStorage::publicImageBytes('posts/a.png', 10));
        $this->assertFalse(is_resource($stream));
    }

    public function test_proposal_upload_is_private_and_download_is_authorized(): void
    {
        [$artist, $client, $answer] = $this->attachment();
        $category = CategoriaOrcamento::create(['id_portfolio_artista' => $answer->proposta->id_artista, 'nome' => 'Work']);
        $question = $answer->pergunta;
        $question->update(['id_categoria_orcamento' => $category->id]);
        $this->actingAs($client)->post(route('propostas.store'), [
            'id_artista' => $answer->proposta->id_artista,
            'anexos' => [$question->id => UploadedFile::fake()->createWithContent('reference.txt', 'hello')],
        ])->assertSessionHasNoErrors()->assertSessionHas('success');
        $saved = RespostaPropostaPergunta::latest('id')->first();
        $this->assertStringStartsWith(MediaStorage::PRIVATE_ATTACHMENTS, $saved->caminho_anexo);
        Storage::disk('private')->assertExists($saved->caminho_anexo);
        Storage::disk('public')->assertMissing($saved->caminho_anexo);
        foreach ([$client, $artist, $this->user(1)] as $user) {
            $this->actingAs($user)->get(route('propostas.anexos.download', $saved))
                ->assertOk()->assertDownload()->assertHeader('X-Content-Type-Options', 'nosniff');
        }
        $this->actingAs($this->user(3))->get(route('propostas.anexos.download', $saved))->assertForbidden();
        $this->actingAs($this->user(2))->get(route('propostas.anexos.download', $saved))->assertForbidden();
    }

    public function test_unauthenticated_deleted_and_legacy_downloads_are_denied(): void
    {
        [$artist, $client, $answer] = $this->attachment();
        $this->get(route('propostas.anexos.download', $answer))->assertRedirect(route('login'));
        $answer->update(['caminho_anexo' => MediaStorage::LEGACY_ATTACHMENTS.'legacy.pdf']);
        Storage::disk('public')->put($answer->caminho_anexo, 'public legacy');
        $this->actingAs($artist)->get(route('propostas.anexos.download', $answer))->assertNotFound();
        $answer->update(['caminho_anexo' => '../other.txt']);
        $this->get(route('propostas.anexos.download', $answer))->assertNotFound();
        $answer->proposta->delete();
        $this->get(route('propostas.anexos.download', $answer))->assertNotFound();
    }

    public function test_s3_download_is_short_lived_and_does_not_proxy_large_objects(): void
    {
        [$artist, , $answer] = $this->attachment();
        config(['filesystems.disks.private.driver' => 's3']);
        $test = $this;
        Storage::disk('private')->buildTemporaryUrlsUsing(function ($path, $expires, $options) use ($answer, $test) {
            $test->assertSame($answer->caminho_anexo, $path);
            $test->assertLessThanOrEqual(60, now()->diffInSeconds($expires));
            $test->assertSame('application/octet-stream', $options['ResponseContentType']);
            return 'https://private.example/download?signature=test';
        });
        $this->actingAs($artist)->get(route('propostas.anexos.download', $answer))
            ->assertRedirect('https://private.example/download?signature=test')->assertHeader('Referrer-Policy', 'no-referrer');
    }

    public function test_total_upload_limit_is_enforced_without_content_length(): void
    {
        config(['uploads.request_max_bytes' => 3 * 1024 * 1024]);
        $request = Request::create('/upload', 'POST', [], [], ['images' => [
            UploadedFile::fake()->create('a.txt', 1800), UploadedFile::fake()->create('b.txt', 1800),
        ]]);
        $this->expectException(ValidationException::class);
        MediaStorage::validateUploadRequest($request);
    }

    public function test_failed_budget_persistence_rolls_back_and_cleans_private_upload(): void
    {
        [, $client, $answer] = $this->attachment();
        $category = CategoriaOrcamento::create(['id_portfolio_artista' => $answer->proposta->id_artista, 'nome' => 'Work']);
        $answer->pergunta->update(['id_categoria_orcamento' => $category->id]);
        $originalFiles = Storage::disk('private')->allFiles();
        RespostaPropostaPergunta::creating(fn () => throw new \RuntimeException('Simulated DB failure'));
        $this->withoutExceptionHandling();
        try {
            $this->actingAs($client)->post(route('propostas.store'), [
                'id_artista' => $answer->proposta->id_artista,
                'anexos' => [$answer->id_pergunta => UploadedFile::fake()->createWithContent('ref.txt', 'bytes')],
            ]);
            $this->fail('Expected a persistence failure.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Simulated DB failure', $error->getMessage());
        } finally {
            RespostaPropostaPergunta::flushEventListeners();
        }
        $this->assertDatabaseCount('proposta_contrato', 1);
        $this->assertDatabaseCount('respostas_proposta_pergunta', 1);
        $this->assertSame($originalFiles, Storage::disk('private')->allFiles());
    }

    public function test_vercel_upload_ceiling_cannot_be_disabled_or_raised(): void
    {
        config(['uploads.vercel' => true, 'uploads.request_max_bytes' => 0]);
        $this->assertSame(3145728, MediaStorage::requestLimit());
        $this->assertSame(3072, MediaStorage::maxFileKilobytes(8192));
        config(['uploads.request_max_bytes' => 10 * 1024 * 1024]);
        $this->assertSame(3145728, MediaStorage::requestLimit());
        config(['uploads.vercel' => false, 'uploads.request_max_bytes' => 0]);
        $this->assertSame(8192, MediaStorage::maxFileKilobytes(8192));
    }

    public function test_oversized_request_is_rejected_before_creating_proposal(): void
    {
        config(['uploads.request_max_bytes' => 3145728]);
        $client = $this->user(3);
        $this->actingAs($client)->post(route('propostas.store'), ['anexos' => [
            UploadedFile::fake()->create('a.txt', 1800), UploadedFile::fake()->create('b.txt', 1800),
        ]])->assertSessionHasErrors('arquivos');
        $this->assertDatabaseCount('proposta_contrato', 0);
        $this->assertSame([], Storage::disk('private')->allFiles());
    }

    public function test_failed_post_edit_keeps_old_image_and_cleans_new_upload(): void
    {
        $artist = $this->user(2);
        $portfolio = PortfolioArtista::create(['id_usuario' => $artist->id]);
        $post = \App\Models\PostPortfolio::create(['id_portfolio' => $portfolio->id, 'nome' => 'Old', 'descricao' => 'Old']);
        $old = \App\Models\PostImagem::create(['post_id' => $post->id, 'caminho_imagem' => 'posts/old.png']);
        Storage::disk('public')->put('posts/old.png', 'old image');
        \App\Models\PostImagem::creating(fn () => throw new \RuntimeException('Simulated image persistence failure'));
        $this->withoutExceptionHandling();
        try {
            $this->actingAs($artist)->put(route('posts.update', $post), [
                'nome' => 'New', 'descricao' => 'New', 'imagens_para_remover' => [$old->id],
                'imagens' => [UploadedFile::fake()->image('new.png')],
            ]);
            $this->fail('Expected a persistence failure.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Simulated image persistence failure', $error->getMessage());
        } finally {
            \App\Models\PostImagem::flushEventListeners();
        }
        $this->assertSame('Old', $post->fresh()->nome);
        $this->assertDatabaseHas('posts_imgs', ['id' => $old->id]);
        $this->assertSame(['posts/old.png'], Storage::disk('public')->allFiles());
    }

    public function test_aggregate_image_limit_is_enforced_before_post_creation(): void
    {
        config(['uploads.request_max_bytes' => 3145728]);
        $artist = $this->user(2);
        PortfolioArtista::create(['id_usuario' => $artist->id]);
        $this->actingAs($artist)->post(route('posts.store'), [
            'nome' => 'New', 'descricao' => 'New', 'imagens' => [
                UploadedFile::fake()->image('a.png')->size(1800), UploadedFile::fake()->image('b.png')->size(1800),
            ],
        ])->assertSessionHasErrors('arquivos');
        $this->assertDatabaseCount('posts_portfolio', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_migration_is_dry_run_then_verified_copy_update_and_delete(): void
    {
        $path = MediaStorage::LEGACY_ATTACHMENTS.'old.txt';
        [, , $answer] = $this->attachment($path);
        Storage::disk('legacy_public')->put($path, 'legacy bytes');
        $this->artisan('storage:privatize-budget-attachments')->assertExitCode(0);
        $this->assertSame($path, $answer->fresh()->caminho_anexo);
        Storage::disk('legacy_public')->assertExists($path);
        $this->artisan('storage:privatize-budget-attachments', ['--apply' => true])->assertExitCode(0);
        $destination = MediaStorage::PRIVATE_ATTACHMENTS.'old.txt';
        $this->assertSame($destination, $answer->fresh()->caminho_anexo);
        $this->assertSame('legacy bytes', Storage::disk('private')->get($destination));
        Storage::disk('legacy_public')->assertMissing($path);
        $this->artisan('storage:privatize-budget-attachments', ['--apply' => true])->assertExitCode(0);
    }

    public function test_migration_never_overwrites_a_conflicting_private_object(): void
    {
        $path = MediaStorage::LEGACY_ATTACHMENTS.'old.txt';
        [, , $answer] = $this->attachment($path);
        Storage::disk('legacy_public')->put($path, 'original');
        Storage::disk('private')->put(MediaStorage::PRIVATE_ATTACHMENTS.'old.txt', 'conflict');
        $this->artisan('storage:privatize-budget-attachments', ['--apply' => true])->assertExitCode(1);
        $this->assertSame($path, $answer->fresh()->caminho_anexo);
        $this->assertSame('original', Storage::disk('legacy_public')->get($path));
    }

    public function test_migration_with_missing_source_retains_references(): void
    {
        $path = MediaStorage::LEGACY_ATTACHMENTS.'missing.txt';
        [, , $answer] = $this->attachment($path);
        $this->artisan('storage:privatize-budget-attachments', ['--apply' => true])->assertExitCode(1);
        $this->assertSame($path, $answer->fresh()->caminho_anexo);
    }

    public function test_deployment_check_blocks_public_references_and_orphan_objects(): void
    {
        $this->assertSame([], StorageDeploymentCheck::issues(false));
        [, , $answer] = $this->attachment(MediaStorage::LEGACY_ATTACHMENTS.'old.txt');
        $this->assertNotEmpty(StorageDeploymentCheck::issues(false));
        $answer->update(['caminho_anexo' => MediaStorage::PRIVATE_ATTACHMENTS.'old.txt']);
        Storage::disk('public')->put(MediaStorage::LEGACY_ATTACHMENTS.'orphan.txt', 'public');
        $this->artisan('storage:check-deployment', ['--local' => true])->assertExitCode(1);
        Storage::disk('public')->deleteDirectory(rtrim(MediaStorage::LEGACY_ATTACHMENTS, '/'));
        $this->artisan('storage:check-deployment', ['--local' => true])->assertExitCode(0);
        $this->assertNotEmpty(StorageDeploymentCheck::issues(true));
    }
}
