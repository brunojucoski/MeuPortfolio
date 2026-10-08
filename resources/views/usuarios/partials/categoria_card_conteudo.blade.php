@if($estiloId === \App\Models\PortfolioArtista::ESTILO_CARD_CATEGORIA_LIVRO)
    <span class="perfil-categoria-book-page">@include('usuarios.partials.categoria_icone', ['icone' => $icone ?? null])<strong>{{ $titulo }}</strong><span>{{ $descricao }}</span></span>
    <span class="perfil-categoria-book-cover">
@endif
<span class="perfil-categoria-preview-stack {{ $imagens->isEmpty() ? 'perfil-categoria-preview-empty' : '' }}" aria-hidden="true">
    @foreach($imagens as $previewIndex => $previewImage)
        <span class="perfil-categoria-preview-foto perfil-categoria-preview-foto-{{ $previewIndex + 1 }}" style="background-image: url('{{ asset('storage/' . $previewImage->caminho_imagem) }}');"></span>
    @endforeach
</span>
<span class="perfil-categoria-overlay" aria-hidden="true"></span>
<span class="perfil-categoria-title">@include('usuarios.partials.categoria_icone', ['icone' => $icone ?? null])<span>{{ $titulo }}</span></span>
<span class="perfil-categoria-description">{{ $descricao }}</span>
@if($estiloId === \App\Models\PortfolioArtista::ESTILO_CARD_CATEGORIA_LIVRO)
    </span>
@elseif($estiloId === \App\Models\PortfolioArtista::ESTILO_CARD_CATEGORIA_PAINEL_3D)
    <span class="perfil-categoria-flip-content">@include('usuarios.partials.categoria_icone', ['icone' => $icone ?? null])<strong>{{ $titulo }}</strong><span>{{ $descricao }}</span></span>
@endif
