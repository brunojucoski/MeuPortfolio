<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

class StorageDeploymentCheck
{
    /** This is a storage-only check, not a certificate of production readiness. */
    public static function issues(bool $production = true): array
    {
        $issues = [];
        $route = Route::getRoutes()->getByName('propostas.anexos.download');
        if (!$route || !collect($route->gatherMiddleware())->contains(fn ($middleware) => $middleware === 'auth' || str_starts_with($middleware, 'auth:'))) {
            $issues[] = 'Missing authenticated route propostas.anexos.download.';
        }
        if ($production) {
            foreach (['public', 'private'] as $disk) {
                $config = config('filesystems.disks.'.$disk, []);
                if (($config['driver'] ?? '') !== 's3') {
                    $issues[] = $disk.' disk must use S3 in Vercel; local writes are not persistent.';
                    continue;
                }
                foreach (['key', 'secret', 'region', 'bucket', 'endpoint'] as $key) {
                    if (empty($config[$key])) $issues[] = 'Missing '.$disk.' disk setting: '.$key.'.';
                }
                if (!str_starts_with($config['endpoint'] ?? '', 'https://')) $issues[] = $disk.' storage endpoint must use HTTPS.';
                if (!empty($config['root'])) $issues[] = $disk.' S3 root must be empty (local paths cannot become bucket prefixes).';
            }
            if (config('filesystems.disks.public.bucket') === config('filesystems.disks.private.bucket')) {
                $issues[] = 'Public and private buckets must be different.';
            }
            if (!str_starts_with(config('filesystems.disks.public.url') ?? '', 'https://')) $issues[] = 'Public storage URL must use HTTPS.';
            if (config('filesystems.disks.private.url')) $issues[] = 'Private storage must not have a public URL.';
            if (!class_exists(\League\Flysystem\AwsS3V3\AwsS3V3Adapter::class)) $issues[] = 'Missing league/flysystem-aws-s3-v3 dependency.';
            if (MediaStorage::requestLimit() <= 0 || MediaStorage::requestLimit() > 3 * 1024 * 1024) {
                $issues[] = 'Set UPLOAD_REQUEST_MAX_BYTES to at most 3145728; larger direct uploads are not implemented.';
            }
        }
        try {
            foreach (DB::table('respostas_proposta_pergunta')->whereNotNull('caminho_anexo')->select('caminho_anexo')->cursor() as $answer) {
                if (!MediaStorage::validKey($answer->caminho_anexo) || !str_starts_with($answer->caminho_anexo, MediaStorage::PRIVATE_ATTACHMENTS)) {
                    $issues[] = 'Legacy or invalid budget attachment references exist. Run storage:privatize-budget-attachments before deployment.';
                    break;
                }
            }
            foreach (['public', 'legacy_public'] as $disk) {
                if (Storage::disk($disk)->allFiles(rtrim(MediaStorage::LEGACY_ATTACHMENTS, '/'))
                    || Storage::disk($disk)->allFiles(rtrim(MediaStorage::PRIVATE_ATTACHMENTS, '/'))) {
                    $issues[] = 'Publicly reachable budget objects remain on '.$disk.'.';
                }
            }
        } catch (\Throwable $error) {
            // Do not print exception messages: SDK failures can include signed URLs or connection data.
            $issues[] = 'Could not audit attachment references/objects. Check storage and database access; deployment is blocked.';
        }
        return $issues;
    }
}
