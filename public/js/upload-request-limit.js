(() => {
    const limit = Number(document.currentScript?.dataset.uploadRequestMaxBytes || 0);
    if (!limit) return;
    document.addEventListener('submit', event => {
        const form = event.target;
        const fields = [...form.querySelectorAll('input[type="file"]')].filter(input => !input.disabled);
        const total = fields.reduce((sum, input) => sum + [...(input.files || [])].reduce((bytes, file) => bytes + file.size, 0), 0);
        const previous = form.querySelector('[data-upload-request-error]');
        if (total <= limit) {
            previous?.remove();
            return;
        }
        event.preventDefault();
        event.stopImmediatePropagation();
        const error = previous || document.createElement('p');
        error.className = 'alert alert-danger';
        error.dataset.uploadRequestError = 'true';
        error.setAttribute('role', 'alert');
        error.setAttribute('tabindex', '-1');
        error.textContent = `Os arquivos somam ${(total / 1048576).toFixed(1)} MiB. O limite total por envio e ${(limit / 1048576).toFixed(1)} MiB. Remova arquivos ou escolha arquivos menores.`;
        if (!previous) form.prepend(error);
        error.focus();
    }, true);
})();
