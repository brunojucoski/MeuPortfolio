@extends('layouts.app')
@section('title', 'Configurações visuais — MeuPortfólio')
@push('styles')
    <link href="{{ asset('css/configuracoes-visuais.css') }}" rel="stylesheet">
@endpush
@section('content')
<form class="visual-settings" method="POST" action="{{ route('admin.configuracoes.update') }}" enctype="multipart/form-data" data-visual-settings>
    @csrf
    @method('PUT')
    <header class="visual-settings-header">
        <h1>Configurações visuais</h1>
        <button type="submit" class="visual-save"><i class="bi bi-check2" aria-hidden="true"></i> Salvar alterações</button>
    </header>
    @if(session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif
    @include('Components.form-validation-summary')
    <section class="visual-section">
        <h2>Identidade</h2>
        <div class="visual-color-row">
            <div>
                <label class="form-label" for="cor_base">Cor base do sistema</label>
                <div class="visual-color-control">
                    <input type="color" name="cor_base" id="cor_base" value="{{ old('cor_base', $visualSistema->corBase()) }}" required>
                    <output for="cor_base" data-color-value>{{ old('cor_base', $visualSistema->corBase()) }}</output>
                    <button type="button" class="visual-icon-button" data-color-reset="{{ config('visual-sistema.cor_base') }}" title="Restaurar cor padrão" aria-label="Restaurar cor padrão"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i></button>
                </div>
            </div>
            <div class="visual-color-sample" data-color-sample>MeuPortfólio</div>
        </div>
        @include('admin.partials.imagem_visual', ['local' => 'logo', 'imagem' => config('visual-sistema.imagens.logo')])
    </section>
    <section class="visual-section">
        <h2>Imagens</h2>
        <div class="visual-image-grid">
            @foreach(config('visual-sistema.imagens') as $local => $imagem)
                @continue($local === 'logo')
                @include('admin.partials.imagem_visual')
            @endforeach
        </div>
    </section>
    <section class="visual-section">
        <h2>Sobre</h2>
        <div class="mb-3">
            <label class="form-label" for="sobre_titulo">Título</label>
            <input class="form-control @error('sobre_titulo') is-invalid @enderror" name="sobre_titulo" id="sobre_titulo" maxlength="120" value="{{ old('sobre_titulo', $visualSistema->sobreTitulo()) }}" required>
        </div>
        <label class="form-label" for="sobre_texto">Texto</label>
        <textarea class="form-control @error('sobre_texto') is-invalid @enderror" name="sobre_texto" id="sobre_texto" rows="7" maxlength="15000" required>{{ old('sobre_texto', $visualSistema->sobreTexto()) }}</textarea>
    </section>
    <div class="visual-settings-footer"><button type="submit" class="visual-save"><i class="bi bi-check2" aria-hidden="true"></i> Salvar alterações</button></div>
</form>
@endsection
@push('scripts')
    <script src="{{ asset('js/configuracoes-visuais.js') }}" defer></script>
@endpush
