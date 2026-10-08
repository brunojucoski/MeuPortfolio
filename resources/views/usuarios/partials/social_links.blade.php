@php($profileLinks = \App\Support\PortfolioSocialLinks::forProfile($portfolio, $usuario->telefone))
@if($profileLinks)
    <nav class="portfolio-social-links" aria-label="Redes sociais e contato">
        @foreach($profileLinks as $link)
            <a class="portfolio-social-link portfolio-social-link--{{ $link['network'] }}" href="{{ $link['url'] }}" target="_blank" rel="noopener noreferrer" aria-label="{{ $link['label'] }}" title="{{ $link['label'] }}">
                <span class="portfolio-social-icon"><i class="bi {{ $link['icon'] }}" aria-hidden="true"></i></span>
                <span class="portfolio-social-background" aria-hidden="true"></span>
                <span class="portfolio-social-tooltip" aria-hidden="true">{{ $link['label'] }}</span>
            </a>
        @endforeach
    </nav>
@endif
