<section class="portfolio-areas" data-area-picker data-search-url="{{ route('areas-atuacao.buscar') }}" data-create-url="{{ route('areas-atuacao.store') }}">
    <h6 class="mb-3">Áreas de atuação</h6>
    <label for="portfolio-area-search" class="form-label">Buscar área de atuação</label>
    <div class="portfolio-area-search-wrap">
        <i class="bi bi-search" aria-hidden="true"></i>
        <input type="search" id="portfolio-area-search" class="form-control" maxlength="45" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="portfolio-area-results" data-area-search>
    </div>
    <div class="portfolio-area-results" id="portfolio-area-results" role="listbox" aria-label="Áreas de atuação encontradas" data-area-results hidden></div>
    <div class="portfolio-area-status" data-area-status role="status" aria-live="polite"></div>
    <div class="portfolio-area-selected" data-area-selected aria-label="Áreas de atuação selecionadas">
        @foreach($categorias->whereIn('id', $portfolioAreasSelecionadas) as $area)
            <span class="portfolio-area-chip" data-area-selected-item data-area-id="{{ $area->id }}" data-area-name="{{ $area->nome }}">
                <input type="hidden" name="categorias[]" value="{{ $area->id }}" form="portfolioSettingsForm">
                <span>{{ $area->nome }}</span>
                <button type="button" data-area-remove="{{ $area->id }}" aria-label="Remover área {{ $area->nome }}" title="Remover área {{ $area->nome }}"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
            </span>
        @endforeach
    </div>
    <button type="button" class="portfolio-area-create-toggle" data-area-create-toggle aria-expanded="false" aria-controls="portfolio-area-create" disabled><i class="bi bi-plus-lg" aria-hidden="true"></i> Cadastrar nova área</button>
    <form class="portfolio-area-create" id="portfolio-area-create" data-area-create hidden>
        <p class="portfolio-area-warning">Cadastre novas áreas de atuação somente se não encontrou nenhum opção compatível</p>
        <label for="portfolio-area-name" class="form-label">Nome da nova área de atuação</label>
        <input type="text" id="portfolio-area-name" class="form-control" name="nome" required minlength="2" maxlength="45" data-area-name>
        <div class="portfolio-area-error" data-area-error role="alert"></div>
        <div class="portfolio-area-create-actions">
            <button type="button" class="btn btn-outline-secondary" data-area-create-cancel>Cancelar</button>
            <button type="submit" class="btn btn-primary-custom" data-area-create-submit><i class="bi bi-check2" aria-hidden="true"></i> Cadastrar e selecionar</button>
        </div>
    </form>
</section>
