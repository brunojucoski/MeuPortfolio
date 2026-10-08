@if($logoUrl = $visualSistema->imagemUrl('logo'))
    <img class="site-brand-image" src="{{ $logoUrl }}" alt="MeuPortfólio" width="190" height="46" decoding="async">
@else
    <span class="site-brand-text">MeuPortfólio</span>
@endif
