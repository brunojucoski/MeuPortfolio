@if($estiloId === \App\Models\PortfolioArtista::ESTILO_CARD_CATEGORIA_LIVRO_EXPANSIVEL)
    <span class="portfolio-album-book">
        <span class="portfolio-album-book-base" aria-hidden="true">
            @include('usuarios.partials.categoria_icone', ['icone' => $icone ?? null])
            <strong>{{ $titulo }}</strong>
            <span class="portfolio-album-book-description">{{ $descricao }}</span>
        </span>
        <span class="portfolio-album-book-inner" aria-hidden="true">
            @include('usuarios.partials.categoria_card_midia', ['imagens' => $imagens->count() > 1 ? $imagens->slice(1)->values() : $imagens])
        </span>
        <span class="portfolio-album-book-front">
            @include('usuarios.partials.categoria_card_midia')
            @include('usuarios.partials.categoria_card_legenda')
        </span>
    </span>
@else
    <span class="portfolio-album-surface">
        @include('usuarios.partials.categoria_card_midia')
        @include('usuarios.partials.categoria_card_legenda')
    </span>
@endif
