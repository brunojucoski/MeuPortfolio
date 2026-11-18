<span class="portfolio-album-caption perfil-categoria-title">
    @include('usuarios.partials.categoria_icone', ['icone' => $icone ?? null])
    <span class="portfolio-album-name" style="--album-title-size: {{ mb_strlen($titulo) > 140 ? 10 : (mb_strlen($titulo) > 80 ? 12 : (mb_strlen($titulo) > 40 ? 16 : 20)) }}px;">{{ $titulo }}</span>
</span>
