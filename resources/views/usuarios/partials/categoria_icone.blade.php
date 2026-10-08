@if($icone && array_key_exists($icone, \App\Support\PortfolioCategoryIcons::all()))
    @if(str_starts_with($icone, 'pixel-'))
        <span class="portfolio-icon portfolio-icon-pixel" style="--pixel-icon: url('{{ asset('icons/pixelart/' . substr($icone, 6) . '.svg') }}');" aria-hidden="true"></span>
    @else
        <i class="bi {{ $icone }} portfolio-icon" aria-hidden="true"></i>
    @endif
@endif
