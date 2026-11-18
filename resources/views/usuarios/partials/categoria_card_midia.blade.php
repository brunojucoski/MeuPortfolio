<span class="portfolio-album-media {{ $imagens->isEmpty() ? 'portfolio-album-media-empty' : '' }}" aria-hidden="true">
    @foreach($imagens->take(3)->values() as $index => $imagem)
        <span class="portfolio-album-photo" style="background-image: url('{{ asset('storage/' . $imagem->caminho_imagem) }}'); --album-photo-layer: {{ 3 - $index }};"></span>
    @endforeach
</span>
