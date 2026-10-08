@php
    $restaurar = old('restaurar_' . $local, false);
    $atual = $visualSistema->imagemUrl($local);
    $padrao = $imagem['padrao'] ? asset($imagem['padrao']) : null;
    $preview = $restaurar ? $padrao : $atual;
@endphp
<div class="visual-image-slot {{ $local === 'logo' ? 'visual-logo-slot' : '' }}" data-image-slot>
    <h3>{{ $imagem['titulo'] }}</h3>
    <div class="visual-image-frame">
        <img class="visual-image-preview" @if($preview) src="{{ $preview }}" @endif alt="{{ $imagem['titulo'] }}" data-image-preview data-current-src="{{ $atual }}" data-default-src="{{ $padrao }}" @if(!$preview) hidden @endif>
        @if($local === 'logo')
            <span class="visual-logo-fallback" data-image-fallback @if($preview) hidden @endif>MeuPortfólio</span>
        @endif
    </div>
    <label class="form-label" for="imagem_{{ $local }}">{{ $local === 'logo' ? 'Novo logo' : 'Nova imagem' }}</label>
    <div class="visual-upload-row">
        <input type="file" class="form-control @error('imagem_' . $local) is-invalid @enderror" name="imagem_{{ $local }}" id="imagem_{{ $local }}" accept="image/jpeg,image/png,image/webp" data-image-input aria-describedby="erro_{{ $local }}">
        <button type="button" class="visual-icon-button" data-image-clear hidden title="Descartar nova imagem" aria-label="Descartar nova imagem de {{ $imagem['titulo'] }}"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
    </div>
    <div class="visual-upload-error" id="erro_{{ $local }}" data-image-error role="status">@error('imagem_' . $local){{ $message }}@enderror</div>
    <label class="visual-image-default"><input type="checkbox" name="restaurar_{{ $local }}" value="1" data-image-default @checked($restaurar)> {{ $local === 'logo' ? 'Usar nome padrão' : 'Imagem padrão' }}</label>
</div>
