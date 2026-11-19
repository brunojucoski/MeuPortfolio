<?php

namespace App\Console\Commands;

use App\Support\MediaStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PrivatizeBudgetAttachments extends Command
{
    protected $signature = 'storage:privatize-budget-attachments {--apply : Copy, verify, update references and delete public sources} {--source=legacy_public : Source disk: legacy_public or public}';
    protected $description = 'Privatize old budget attachments; dry run by default. Run with writes paused and a backup.';

    public function handle(): int
    {
        $sourceName = $this->option('source');
        if (!in_array($sourceName, ['legacy_public', 'public'], true)) {
            $this->error('Source must be legacy_public or public.');
            return self::FAILURE;
        }
        $source = Storage::disk($sourceName);
        $target = Storage::disk('private');
        $failed = false;
        $paths = DB::table('respostas_proposta_pergunta')->whereNotNull('caminho_anexo')
            ->where('caminho_anexo', 'like', MediaStorage::LEGACY_ATTACHMENTS.'%')
            ->distinct()->orderBy('caminho_anexo')->pluck('caminho_anexo');
        foreach ($paths as $path) {
            try {
                if (!MediaStorage::validKey($path) || !$source->exists($path)) {
                    throw new \RuntimeException('Invalid or missing source. No reference was changed.');
                }
                $destination = MediaStorage::PRIVATE_ATTACHMENTS.substr($path, strlen(MediaStorage::LEGACY_ATTACHMENTS));
                $this->line(($this->option('apply') ? 'Migrate: ' : 'Would migrate: ').$path);
                if (!$this->option('apply')) continue;

                $originalHash = $this->digest($source, $path);
                if ($target->exists($destination)) {
                    if ($this->digest($target, $destination) !== $originalHash) {
                        throw new \RuntimeException('Destination already exists with different content.');
                    }
                } else {
                    $stream = $source->readStream($path);
                    if (!is_resource($stream)) throw new \RuntimeException('Could not open source.');
                    try {
                        if (!$target->put($destination, $stream, MediaStorage::writeOptions('private') + ['mimetype' => 'application/octet-stream',
                            'ContentType' => 'application/octet-stream', 'ContentDisposition' => 'attachment', 'CacheControl' => 'private, no-store'])) {
                            throw new \RuntimeException('Could not copy to private storage.');
                        }
                    } finally {
                        fclose($stream);
                    }
                }
                if ($source->size($path) !== $target->size($destination)
                    || $this->digest($target, $destination) !== $originalHash
                    || $this->digest($source, $path) !== $originalHash) {
                    throw new \RuntimeException('Verification failed. Public source was retained.');
                }
                DB::transaction(function () use ($path, $destination) {
                    DB::table('respostas_proposta_pergunta')->where('caminho_anexo', $path)
                        ->update(['caminho_anexo' => $destination]);
                });
                if (!$source->delete($path)) {
                    throw new \RuntimeException('References migrated but public source deletion failed. Remove it before deployment.');
                }
            } catch (\Throwable $error) {
                $failed = true;
                $this->error('Migration failed for '.$path.'. No deploy until the source and destination are reviewed.');
                report($error);
            }
        }
        // Orphans and failed deletions also remain publicly reachable and must never be ignored.
        try {
            $remaining = $source->allFiles(rtrim(MediaStorage::LEGACY_ATTACHMENTS, '/'));
        } catch (\Throwable $error) {
            $this->error('Could not audit remaining public objects. Deployment is blocked.');
            return self::FAILURE;
        }
        if ($remaining) {
            $this->warn(count($remaining).' public legacy object(s) remain. Review references and backups before manual removal.');
            if ($this->option('apply')) $failed = true;
        }
        $this->info($this->option('apply') ? 'Migration pass finished.' : 'Simulation only: no data or file changed.');
        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function digest($disk, string $path): string
    {
        $stream = $disk->readStream($path);
        if (!is_resource($stream)) throw new \RuntimeException('Could not verify object.');
        try {
            $hash = hash_init('sha256');
            if (hash_update_stream($hash, $stream) === false) throw new \RuntimeException('Could not hash object.');
            return hash_final($hash);
        } finally {
            fclose($stream);
        }
    }
}
