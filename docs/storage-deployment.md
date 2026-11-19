# Storage deployment contract

This change prepares storage; it does not publish the application or verify a real Supabase project.

## Configuration

Development keeps the `public` disk at `storage/app/public` and the new `private` disk at `storage/app/private`. Only public files may use `public/storage`. Never create a link for the private disk.

Production uses the same disk names, backed by two different Supabase Storage buckets:

```dotenv
PUBLIC_STORAGE_DRIVER=s3
PRIVATE_STORAGE_DRIVER=s3
PUBLIC_STORAGE_BUCKET=portfolio-public
PRIVATE_STORAGE_BUCKET=budget-private
PUBLIC_STORAGE_URL=https://PROJECT.supabase.co/storage/v1/object/public/portfolio-public
STORAGE_S3_ENDPOINT=https://PROJECT.storage.supabase.co/storage/v1/s3
STORAGE_S3_REGION=REGION_FROM_SUPABASE
STORAGE_S3_KEY=SERVER_SIDE_S3_ACCESS_KEY
STORAGE_S3_SECRET=SERVER_SIDE_S3_SECRET
UPLOAD_REQUEST_MAX_BYTES=3145728
```

Copy the endpoint and region from Supabase, rather than assuming a region. Do not put credentials in JavaScript, Blade HTML, public environment variables, Git or uploaded configuration files. Generated S3 credentials bypass Supabase RLS and must stay on the Laravel server. No Supabase Auth is used.

The public bucket is public. The budget bucket **must be private** in Supabase. Do not grant anonymous/authenticated Storage API policies that allow reading or listing budget objects. Bucket privacy is not established by S3 object ACLs; this code omits unsupported ACL headers. Confirm denial with an anonymous download/list request before release. That live check has not been run here.

## Authorized download route

The web routes include this authenticated endpoint:

```php
Route::get('/propostas/anexos/{resposta}/download', [PropostaContratoController::class, 'downloadAnexo'])
    ->middleware('auth')->name('propostas.anexos.download');
```

Budget links use this route, never `Storage::url`. It verifies the answer's proposal and the authenticated participant/admin before reading the object. Legacy public paths are not served as a fallback. On S3, download redirects to a signed URL valid for 60 seconds; uploaded objects carry download-only metadata. A signed link is a short-lived bearer credential: do not log it or place it in analytics. Locally the endpoint streams a private download.

The aggregate deployment command can merge `App\Support\StorageDeploymentCheck::issues(true)` into its blocking findings, or run `php artisan storage:check-deployment`. This is not an overall production-readiness certificate. It must run against the intended database and storage, after migrations and before release, and never in a public HTTP endpoint. Scanning storage makes authenticated read/list calls when actually run on remote disks.

## Existing files and attachment migration

1. Pause writes and back up the database **and files**. Inspect all storage sources and any public CDN caches. Do not run destructive database resets.
2. Keep the original local `storage/app/public` directory available. `legacy_public` always points to it, regardless of the new public disk driver.
3. Run `php artisan storage:privatize-budget-attachments` for simulation. It does not write, delete or change records.
4. Review the result, then run `php artisan storage:privatize-budget-attachments --apply`. For objects already in the currently configured public disk use `--source=public` instead. If copies exist in both sources, both must be reviewed and cleaned; migration of one copy does not revoke another public copy.
5. The command copies to `propostas_anexos_privados/` on the private disk, verifies SHA-256 and size, updates all matching references, then deletes the original public object. A conflicting target is never overwritten. Failure retains source/references wherever possible and exits nonzero. Resume is safe for verified existing targets; public orphans/failed deletions require manual review and removal with a backup.
6. **Do not sync the entire public disk to the public bucket.** Transfer only reviewed public media (posts, profile photos, system images and known legacy public media), keeping relative object keys. Never copy either budget attachment prefix to a public bucket. Verify counts and hashes before switching public URLs. Keep backups private and outside the deployment image/repository.
7. Remove remaining public copies/orphans only after verifying references and backups. Purge previously public attachment URLs from any CDN; moving an object does not retract copies already downloaded.
8. Run `php artisan storage:check-deployment`. It rejects old/invalid attachment references, public attachment objects on either public source, non-S3 production disks, shared buckets, missing settings and incompatible upload limits. It does not prove bucket privacy or validate all public-media copies; complete those live checks separately.

## Deliberate upload limitation

Direct-to-Storage uploads and finalization are **not implemented** in this delivery. On Vercel the application now permits at most **3 MiB of files per entire multipart request**, not 3 MiB per image. The server checks aggregate file bytes and, where available, Content-Length, with 512 KiB multipart/text headroom. The browser blocks oversized selected file sets before submitting. Existing 8 MiB image and 10 MiB attachment limits remain only for unrestricted local development. The Vercel cap cannot be raised through UPLOAD_REQUEST_MAX_BYTES.

Use production PHP `upload_max_filesize=3M` and `post_max_size=4M`. Upstream requests exceeding the platform limit may be rejected before PHP runs; client validation is usability, not a security boundary. Publish only with this reduced capability explicitly accepted. Restoring large uploads is a separate release blocker: it requires authorized short-lived upload intents, private quarantine, bounded MIME/size verification of actual server-read object bytes, ownership/replay checks and atomic finalization. Merely accepting a browser-supplied object key is unsafe.

PDF image loading uses bounded reads from the configured disk and keeps the remote-HTTP feature disabled. Very large generated PDF responses must also be validated with the chosen Vercel runtime; this storage work does not lift response/body constraints.

## Validation performed locally

SQLite in-memory tests and fake disks cover authorization, private uploads, rollback cleanup, verified legacy migration, collision/missing-source refusal, deployment audit and aggregate upload limits. An actual AWS/Flysystem adapter with an SDK MockHandler verifies HTTP serialization without any cloud calls. Real Supabase credentials, bucket permissions, upload/download and redeploy persistence remain to be tested after account creation.

References: [Supabase S3 authentication](https://supabase.com/docs/guides/storage/s3/authentication), [S3 compatibility](https://supabase.com/docs/guides/storage/s3/compatibility), [Vercel function limits](https://vercel.com/docs/functions/limitations).
