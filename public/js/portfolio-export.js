document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('portfolioExportModal');
    const editor = document.getElementById('editModalportfolio');
    const open = document.querySelector('[data-portfolio-export-open]');
    const form = document.getElementById('portfolioExportForm');
    if (!modal || !editor || !open || !form) return;
    const groups = [...modal.querySelectorAll('[data-export-group]')];
    const status = modal.querySelector('[data-export-status]');
    const error = modal.querySelector('[data-export-error]');
    const submit = modal.querySelector('[data-export-submit]');
    const download = modal.querySelector('[data-export-download]');
    let pending = false;
    let controller;
    let preview;
    let objectUrl;
    let reopenEditor = false;
    let generation = 0;

    function sync(group) {
        const album = group.querySelector('[data-export-album]');
        const posts = [...group.querySelectorAll('[data-export-post]')];
        if (!posts.length) return;
        const count = posts.filter(post => post.checked).length;
        album.checked = count === posts.length;
        album.indeterminate = count > 0 && count < posts.length;
    }
    function count() {
        const posts = [...modal.querySelectorAll('[data-export-post]')].filter(item => item.checked).length;
        status.textContent = `${posts} ${posts === 1 ? 'post selecionado' : 'posts selecionados'}.`;
    }
    function revoke() {
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        objectUrl = null;
        download.hidden = true;
        download.removeAttribute('href');
    }
    groups.forEach(group => {
        const album = group.querySelector('[data-export-album]');
        album.addEventListener('change', () => {
            group.querySelectorAll('[data-export-post]').forEach(post => { post.checked = album.checked; });
            album.indeterminate = false;
            count();
        });
        group.querySelectorAll('[data-export-post]').forEach(post => post.addEventListener('change', () => { sync(group); count(); }));
    });
    function selectAll(checked) {
        modal.querySelectorAll('[data-export-post], [data-export-album]').forEach(item => { item.checked = checked; item.indeterminate = false; });
        count();
    }
    modal.querySelector('[data-export-all]').addEventListener('click', () => selectAll(true));
    modal.querySelector('[data-export-clear]').addEventListener('click', () => selectAll(false));
    open.addEventListener('click', () => {
        error.hidden = true;
        reopenEditor = true;
        editor.dataset.portfolioExporting = 'true';
        const show = () => bootstrap.Modal.getOrCreateInstance(modal).show();
        editor.addEventListener('hidden.bs.modal', show, { once: true });
        bootstrap.Modal.getOrCreateInstance(editor).hide();
    });
    modal.addEventListener('hidden.bs.modal', () => {
        generation++;
        controller?.abort();
        if (pending) preview?.close();
        delete editor.dataset.portfolioExporting;
        if (reopenEditor) bootstrap.Modal.getOrCreateInstance(editor).show();
        reopenEditor = false;
    });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (pending) return;
        error.hidden = true;
        const data = new FormData(form);
        if (!['campos[]', 'redes[]', 'posts[]', 'albuns[]'].some(name => data.getAll(name).length)) {
            error.textContent = 'Selecione pelo menos um item para exportar.';
            error.hidden = false;
            return;
        }
        revoke();
        // Open synchronously from the click to avoid popup blockers during PDF generation.
        preview = window.open('', '_blank');
        if (preview) {
            preview.opener = null;
            preview.document.title = 'Gerando portfólio';
            preview.document.body.textContent = 'Gerando seu portfólio em PDF…';
        }
        const currentPreview = preview;
        const currentGeneration = generation;
        pending = true;
        submit.disabled = true;
        status.textContent = 'Gerando PDF…';
        controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 90000);
        try {
            const response = await fetch(form.action, { method: 'POST', body: data, credentials: 'same-origin', headers: { Accept: 'application/json' }, signal: controller.signal });
            if (!response.ok) {
                const body = await response.json().catch(() => ({}));
                const message = [401, 419].includes(response.status) ? 'Sua sessão expirou. Entre novamente para exportar.'
                    : response.status === 429 ? 'Aguarde um minuto antes de exportar novamente.'
                    : Object.values(body.errors || {}).flat()[0] || body.message || 'Não foi possível gerar o PDF. Tente novamente.';
                throw new Error(message);
            }
            if (!(response.headers.get('Content-Type') || '').includes('application/pdf')) throw new Error('A sessão não está disponível. Entre novamente para exportar.');
            const blob = await response.blob();
            if (currentGeneration !== generation) return;
            objectUrl = URL.createObjectURL(blob);
            if (currentPreview && !currentPreview.closed) currentPreview.location.replace(objectUrl);
            download.href = objectUrl;
            download.hidden = false;
            status.textContent = 'PDF gerado.';
        } catch (failure) {
            currentPreview?.close();
            if (currentGeneration !== generation) return;
            error.textContent = failure.name === 'AbortError' ? 'A exportação demorou demais. Selecione menos trabalhos e tente novamente.' : failure.message;
            error.hidden = false;
            count();
        } finally {
            clearTimeout(timeout);
            pending = false;
            submit.disabled = false;
        }
    });
    window.addEventListener('pagehide', revoke);
    count();
});
