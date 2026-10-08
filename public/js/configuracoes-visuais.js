(function () {
    const form = document.querySelector('[data-visual-settings]');
    if (!form) return;
    const color = form.querySelector('#cor_base');
    const output = form.querySelector('[data-color-value]');
    const sample = form.querySelector('[data-color-sample]');
    const reset = form.querySelector('[data-color-reset]');
    function previewColor() {
        output.textContent = color.value;
        sample.style.setProperty('--preview-color', color.value);
    }
    color.addEventListener('input', previewColor);
    reset.addEventListener('click', function () {
        color.value = reset.dataset.colorReset;
        previewColor();
    });
    previewColor();

    const cleanups = [];
    form.querySelectorAll('[data-image-slot]').forEach(function (slot) {
        const input = slot.querySelector('[data-image-input]');
        const preview = slot.querySelector('[data-image-preview]');
        const clear = slot.querySelector('[data-image-clear]');
        const defaults = slot.querySelector('[data-image-default]');
        const error = slot.querySelector('[data-image-error]');
        const fallback = slot.querySelector('[data-image-fallback]');
        let objectUrl = null;
        function showPreview(src) {
            if (src) preview.src = src;
            else preview.removeAttribute('src');
            preview.hidden = !src;
            if (fallback) fallback.hidden = !!src;
        }
        function release() {
            if (objectUrl) URL.revokeObjectURL(objectUrl);
            objectUrl = null;
        }
        function restore() {
            release();
            input.value = '';
            showPreview(defaults.checked ? preview.dataset.defaultSrc : preview.dataset.currentSrc);
            clear.hidden = true;
            error.textContent = '';
        }
        input.addEventListener('change', function () {
            const file = input.files[0];
            if (!file) { restore(); return; }
            if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 2 * 1024 * 1024) {
                restore();
                error.textContent = 'Selecione uma imagem JPG, PNG ou WebP de até 2 MB.';
                return;
            }
            defaults.checked = false;
            release();
            objectUrl = URL.createObjectURL(file);
            showPreview(objectUrl);
            clear.hidden = false;
            error.textContent = '';
        });
        clear.addEventListener('click', restore);
        defaults.addEventListener('change', restore);
        cleanups.push(release);
    });
    window.addEventListener('pagehide', event => {
        if (!event.persisted) cleanups.forEach(cleanup => cleanup());
    });
})();
