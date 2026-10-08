document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-area-picker]');
    if (!root) return;
    const search = root.querySelector('[data-area-search]');
    const results = root.querySelector('[data-area-results]');
    const status = root.querySelector('[data-area-status]');
    const chips = root.querySelector('[data-area-selected]');
    const toggle = root.querySelector('[data-area-create-toggle]');
    const createForm = root.querySelector('[data-area-create]');
    const name = createForm.querySelector('[data-area-name]');
    const error = root.querySelector('[data-area-error]');
    const submit = root.querySelector('[data-area-create-submit]');
    const cancel = root.querySelector('[data-area-create-cancel]');
    const settingsForm = document.getElementById('portfolioSettingsForm');
    const initial = [...chips.querySelectorAll('[data-area-selected-item]')].map(item => ({ id: item.dataset.areaId, nome: item.dataset.areaName }));
    let selected = new Map(initial.map(area => [String(area.id), area]));
    let found = [];
    let active = -1;
    let timer;
    let controller;
    let version = 0;
    let generation = 0;
    let creating = false;

    function closeResults() {
        results.hidden = true;
        search.setAttribute('aria-expanded', 'false');
        search.removeAttribute('aria-activedescendant');
        active = -1;
    }
    function stopSearch() {
        clearTimeout(timer);
        controller?.abort();
        version++;
    }
    function hideCreate() {
        createForm.hidden = true;
        toggle.setAttribute('aria-expanded', 'false');
        error.textContent = '';
    }
    function renderSelected() {
        chips.replaceChildren();
        selected.forEach((area, id) => {
            const chip = document.createElement('span');
            chip.className = 'portfolio-area-chip';
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'categorias[]';
            hidden.setAttribute('value', id);
            hidden.setAttribute('form', 'portfolioSettingsForm');
            const label = document.createElement('span');
            label.textContent = area.nome;
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.dataset.areaRemove = id;
            remove.setAttribute('aria-label', `Remover área ${area.nome}`);
            remove.title = `Remover área ${area.nome}`;
            const icon = document.createElement('i');
            icon.className = 'bi bi-x-lg';
            icon.setAttribute('aria-hidden', 'true');
            remove.append(icon);
            chip.append(hidden, label, remove);
            chips.append(chip);
        });
    }
    function select(area) {
        if (!area || !/^\d+$/.test(String(area.id)) || typeof area.nome !== 'string') return;
        const id = String(area.id);
        if (!selected.has(id) && selected.size >= 100) {
            status.textContent = 'O limite é de 100 áreas de atuação.';
            return;
        }
        selected.set(id, area);
        renderSelected();
        stopSearch();
        search.value = '';
        toggle.disabled = true;
        found = [];
        closeResults();
        status.textContent = `${area.nome} selecionada.`;
        search.focus();
    }
    function activate(index) {
        active = index;
        [...results.children].forEach((option, i) => option.setAttribute('aria-selected', String(i === active)));
        if (active >= 0) {
            search.setAttribute('aria-activedescendant', `portfolio-area-option-${active}`);
            results.children[active]?.scrollIntoView({ block: 'nearest' });
        }
    }
    async function load(term, requestVersion) {
        controller = new AbortController();
        const currentController = controller;
        const timeout = setTimeout(() => currentController.abort(), 10000);
        status.textContent = 'Buscando áreas de atuação…';
        try {
            const url = new URL(root.dataset.searchUrl, window.location.href);
            url.searchParams.set('q', term);
            const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin', signal: currentController.signal });
            if (!response.ok) throw new Error('search');
            const body = await response.json();
            if (requestVersion !== version) return;
            found = (Array.isArray(body.data) ? body.data : []).filter(area => !selected.has(String(area.id)));
            results.replaceChildren();
            found.forEach((area, index) => {
                const option = document.createElement('button');
                option.type = 'button';
                option.tabIndex = -1;
                option.id = `portfolio-area-option-${index}`;
                option.dataset.areaIndex = String(index);
                option.setAttribute('role', 'option');
                option.setAttribute('aria-selected', 'false');
                option.textContent = area.nome;
                results.append(option);
            });
            results.hidden = found.length === 0;
            search.setAttribute('aria-expanded', String(found.length > 0));
            status.textContent = found.length ? (body.more ? 'Mais resultados disponíveis. Refine a busca.' : `${found.length} ${found.length === 1 ? 'área encontrada' : 'áreas encontradas'}.`)
                : (body.data?.length ? 'As áreas encontradas já estão selecionadas.' : 'Nenhuma área de atuação encontrada.');
            active = -1;
        } catch (failure) {
            if (requestVersion !== version) return;
            closeResults();
            status.textContent = 'Não foi possível buscar as áreas. Tente novamente.';
        } finally {
            clearTimeout(timeout);
        }
    }
    search.addEventListener('input', () => {
        stopSearch();
        found = [];
        closeResults();
        const term = search.value.trim();
        toggle.disabled = [...term].length < 2 || creating;
        status.textContent = '';
        if ([...term].length < 2) return;
        const requestVersion = version;
        timer = setTimeout(() => load(term, requestVersion), 250);
    });
    search.addEventListener('focus', () => {
        const term = search.value.trim();
        if ([...term].length < 2 || creating) return;
        stopSearch();
        const requestVersion = version;
        timer = setTimeout(() => load(term, requestVersion), 250);
    });
    search.addEventListener('keydown', event => {
        if (event.key === 'Enter') {
            event.preventDefault();
            if (!results.hidden && found.length) select(found[active < 0 ? 0 : active]);
        } else if (['ArrowDown', 'ArrowUp'].includes(event.key) && found.length && !results.hidden) {
            event.preventDefault();
            const next = active < 0 ? (event.key === 'ArrowDown' ? 0 : found.length - 1)
                : (active + (event.key === 'ArrowDown' ? 1 : -1) + found.length) % found.length;
            activate(next);
        } else if (event.key === 'Escape' && !results.hidden) {
            event.preventDefault();
            event.stopPropagation();
            stopSearch();
            closeResults();
        }
    });
    results.addEventListener('click', event => {
        const option = event.target.closest('[data-area-index]');
        if (option) select(found[Number(option.dataset.areaIndex)]);
    });
    chips.addEventListener('click', event => {
        const remove = event.target.closest('[data-area-remove]');
        if (!remove) return;
        const area = selected.get(remove.dataset.areaRemove);
        selected.delete(remove.dataset.areaRemove);
        renderSelected();
        status.textContent = `${area?.nome || 'Área'} removida.`;
        search.focus();
    });
    root.addEventListener('focusout', event => {
        if (!root.contains(event.relatedTarget)) { stopSearch(); closeResults(); }
    });
    toggle.addEventListener('click', () => {
        stopSearch();
        closeResults();
        createForm.hidden = false;
        toggle.setAttribute('aria-expanded', 'true');
        name.value = search.value.trim();
        error.textContent = '';
        name.focus();
    });
    cancel.addEventListener('click', () => { hideCreate(); search.focus(); });
    createForm.addEventListener('submit', async event => {
        event.preventDefault();
        if (creating) return;
        const currentGeneration = generation;
        creating = true;
        submit.disabled = cancel.disabled = toggle.disabled = true;
        name.readOnly = true;
        error.textContent = '';
        const createController = new AbortController();
        const timeout = setTimeout(() => createController.abort(), 15000);
        try {
            const response = await fetch(root.dataset.createUrl, {
                method: 'POST', credentials: 'same-origin', signal: createController.signal,
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body: JSON.stringify({ nome: name.value.trim(), confirmacao: true }),
            });
            const body = await response.json();
            if (currentGeneration !== generation) return;
            if ([401, 419].includes(response.status)) {
                error.textContent = 'Sua sessão expirou. Atualize a página e entre novamente.';
                return;
            }
            if (!response.ok) {
                error.textContent = body.errors?.nome?.[0] || body.message || 'Não foi possível cadastrar a área.';
                return;
            }
            select(body.data);
            hideCreate();
        } catch (failure) {
            if (currentGeneration === generation) error.textContent = 'Não foi possível cadastrar a área. Verifique a conexão e tente novamente.';
        } finally {
            clearTimeout(timeout);
            creating = false;
            submit.disabled = cancel.disabled = false;
            name.readOnly = false;
            toggle.disabled = [...search.value.trim()].length < 2;
        }
    });
    settingsForm?.addEventListener('submit', event => {
        if (creating) { event.preventDefault(); status.textContent = 'Aguarde o cadastro da área de atuação.'; }
    });
    settingsForm?.addEventListener('reset', () => {
        generation++;
        stopSearch();
        selected = new Map(initial.map(area => [String(area.id), area]));
        renderSelected();
        search.value = '';
        status.textContent = '';
        found = [];
        closeResults();
        hideCreate();
        toggle.disabled = true;
    });
    renderSelected();
});
