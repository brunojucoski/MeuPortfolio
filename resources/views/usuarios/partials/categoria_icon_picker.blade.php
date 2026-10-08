<fieldset class="portfolio-icon-picker mt-3" data-icon-picker>
    <legend class="form-label fs-6">Ícone do álbum</legend>
    <div class="portfolio-icon-filters" role="group" aria-label="Estilo dos ícones">
        <button type="button" class="portfolio-icon-filter is-active" data-icon-filter="" aria-pressed="true">Todos</button>
        <button type="button" class="portfolio-icon-filter" data-icon-filter="Clássicos" aria-pressed="false">Clássicos</button>
        <button type="button" class="portfolio-icon-filter" data-icon-filter="Pixel art" aria-pressed="false">Pixel art</button>
    </div>
    <label class="visually-hidden" for="{{ $pickerId }}_search">Buscar ícone</label>
    <input id="{{ $pickerId }}_search" type="search" class="form-control form-control-sm mb-2" placeholder="Buscar ícone" data-icon-search>
    <div class="portfolio-icon-options">
        <label class="portfolio-icon-option" title="Sem ícone" data-icon-option data-icon-name="Sem ícone" data-icon-group="">
            <input type="radio" name="icone" value="" checked aria-label="Sem ícone">
            <i class="bi bi-slash-circle" aria-hidden="true"></i>
        </label>
        @foreach(\App\Support\PortfolioCategoryIcons::all() as $iconeKey => $iconeOption)
            <label class="portfolio-icon-option" title="{{ $iconeOption['nome'] }} · {{ $iconeOption['grupo'] }}" data-icon-option data-icon-name="{{ $iconeOption['nome'] }}" data-icon-group="{{ $iconeOption['grupo'] }}">
                <input type="radio" name="icone" value="{{ $iconeKey }}" aria-label="{{ $iconeOption['nome'] }} · {{ $iconeOption['grupo'] }}">
                @include('usuarios.partials.categoria_icone', ['icone' => $iconeKey])
            </label>
        @endforeach
    </div>
    <p class="small text-muted mt-2 mb-0" data-icon-selected>Sem ícone</p>
    <p class="small text-muted mt-2 mb-0" data-icon-empty hidden>Nenhum ícone encontrado.</p>
</fieldset>
