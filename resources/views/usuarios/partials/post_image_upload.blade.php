@php
    $uploadLabel = $uploadLabel ?? 'Imagens';
    $phpPostLimit = trim((string) ini_get('post_max_size'));
    $unit = strtolower(substr($phpPostLimit, -1));
    $postLimitBytes = (int) $phpPostLimit * (['g' => 1073741824, 'm' => 1048576, 'k' => 1024][$unit] ?? 1);
    $uploadTotalLimit = $postLimitBytes > 0 ? max(0, $postLimitBytes - 1048576) : 0;
    $uploadFileLimit = min(20, (int) ini_get('max_file_uploads') ?: 20);
@endphp
<div class="post-image-upload mb-3" data-post-image-upload data-max-files="{{ $uploadFileLimit }}" data-max-size="8388608" data-max-total="{{ $uploadTotalLimit }}">
    <p class="form-label mb-2">{{ $uploadLabel }}</p>
    <label class="post-image-dropzone" for="{{ $inputId }}">
        <i class="bi bi-cloud-arrow-up" aria-hidden="true"></i>
        <span class="post-image-dropzone-title">Selecionar imagens</span>
        <span class="post-image-dropzone-formats" id="{{ $inputId }}_help">JPG, PNG ou GIF · Até {{ $uploadFileLimit }} imagens de 8 MB</span>
        <input type="file" name="imagens[]" id="{{ $inputId }}" multiple accept="image/jpeg,image/png,image/gif,.jpg,.jpeg,.png,.gif" aria-label="{{ $uploadLabel }}" aria-describedby="{{ $inputId }}_help">
    </label>
    <p class="post-image-upload-error mb-0" data-upload-error role="alert" hidden></p>
    <p class="post-image-upload-status small text-muted mb-0" data-upload-status aria-live="polite" aria-atomic="true"></p>
    <ul class="post-image-previews" data-upload-previews aria-label="Imagens selecionadas"></ul>
</div>
