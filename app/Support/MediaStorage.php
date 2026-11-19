<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class MediaStorage
{
    public const PRIVATE_ATTACHMENTS = 'propostas_anexos_privados/';
    public const LEGACY_ATTACHMENTS = 'propostas_anexos/';

    public static function validKey(?string $path): bool
    {
        return is_string($path) && preg_match('~\A[a-zA-Z0-9_./-]+\z~D', $path)
            && !str_starts_with($path, '/')
            && !array_intersect(explode('/', $path), ['', '.', '..']);
    }

    public static function publicUrl(?string $path): ?string
    {
        if (!self::isPublicKey($path)) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    private static function isPublicKey(?string $path): bool
    {
        return self::validKey($path)
            && !preg_match('~\A(?:propostas_anexos/|propostas_anexos_privados/|private/)~i', $path);
    }

    public static function store(UploadedFile $file, string $directory, string $disk = 'public'): string
    {
        $options = ['disk' => $disk] + self::writeOptions($disk);
        if ($disk === 'private') {
            $options += ['mimetype' => 'application/octet-stream', 'ContentType' => 'application/octet-stream',
                'ContentDisposition' => 'attachment', 'CacheControl' => 'private, no-store'];
        }
        $path = $file->store($directory, $options);
        if (!$path) {
            throw new \RuntimeException('Nao foi possivel armazenar o arquivo.');
        }

        return $path;
    }

    public static function writeOptions(string $disk): array
    {
        if (config('filesystems.disks.'.$disk.'.driver') !== 's3') return [];

        // Supabase does not support object ACLs. Bucket settings control public/private access.
        return ['mup_threshold' => PHP_INT_MAX, 'before_upload' => static function ($command) {
            unset($command['ACL']);
        }];
    }

    public static function requestLimit(): int
    {
        $configured = max(0, (int) config('uploads.request_max_bytes', 0));
        // Never let a mistakenly raised override bypass the platform's body ceiling.
        return config('uploads.vercel', false)
            ? ($configured > 0 ? min($configured, 3 * 1024 * 1024) : 3 * 1024 * 1024)
            : $configured;
    }

    public static function maxFileKilobytes(int $localMaximum): int
    {
        $limit = self::requestLimit();
        return $limit > 0 ? min($localMaximum, intdiv($limit, 1024)) : $localMaximum;
    }

    public static function validateUploadRequest(Request $request): void
    {
        $limit = self::requestLimit();
        if (!$limit) return;

        $total = 0;
        $files = $request->allFiles();
        array_walk_recursive($files, function ($file) use (&$total) {
            if ($file instanceof UploadedFile && $file->isValid()) $total += $file->getSize();
        });
        // Content-Length also covers text and multipart overhead, when provided by the server.
        if ($total > $limit || (int) $request->server('CONTENT_LENGTH', 0) > $limit + 512 * 1024) {
            throw ValidationException::withMessages([
                'arquivos' => 'O envio completo excede o limite desta hospedagem ('.round($limit / 1048576, 1).' MiB). Envie menos arquivos ou arquivos menores.',
            ]);
        }
    }

    /** Read bounded bytes from the configured disk; never fetch arbitrary HTTP URLs for PDF. */
    public static function publicImageBytes(?string $path, int $maximum = 20 * 1024 * 1024): ?string
    {
        if (!self::isPublicKey($path)) return null;
        $disk = Storage::disk('public');
        if (!$disk->exists($path)) return null;
        // Local realpaths also reject symlinks that escape the public disk.
        if (config('filesystems.disks.public.driver') === 'local') {
            $root = realpath($disk->path(''));
            $resolved = realpath($disk->path($path));
            if (!$root || !$resolved || !str_starts_with($resolved, $root.DIRECTORY_SEPARATOR)) return null;
        }
        if ($disk->size($path) > $maximum) return null;
        $stream = $disk->readStream($path);
        if (!is_resource($stream)) return null;
        try {
            $bytes = stream_get_contents($stream, $maximum + 1);
            return $bytes !== false && strlen($bytes) <= $maximum ? $bytes : null;
        } finally {
            fclose($stream);
        }
    }
}
