document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('editModalportfolio');
    if (!modal) return;
    const inputs = [...modal.querySelectorAll('[data-portfolio-color]')];
    const originalColors = inputs.map(input => input.value);
    const applyPreview = () => inputs.forEach(input => modal.style.setProperty(input.dataset.portfolioColor, input.value));
    inputs.forEach(input => input.addEventListener('input', applyPreview));
    modal.querySelector('[data-portfolio-color-reset]')?.addEventListener('click', event => {
        const reset = event.currentTarget;
        inputs.forEach((input, index) => { input.value = [reset.dataset.primaryDefault || '#6d2e2e', reset.dataset.secondaryDefault || '#8f4444'][index]; });
        applyPreview();
    });
    modal.addEventListener('hidden.bs.modal', () => {
        // Keep the public profile unchanged until the artist saves the settings.
        form?.reset();
        inputs.forEach((input, index) => { input.value = originalColors[index]; });
        applyPreview();
    });
    const form = document.getElementById('portfolioSettingsForm');
    modal.addEventListener('invalid', event => {
        const panel = event.target.closest('.tab-pane');
        if (!panel) return;
        const tab = modal.querySelector(`[data-bs-target="#${panel.id}"]`);
        if (tab) bootstrap.Tab.getOrCreateInstance(tab).show();
    }, true);
    applyPreview();
    const errors = modal.querySelector('[data-portfolio-errors]');
    modal.addEventListener('show.bs.modal', () => {
        if (!errors) bootstrap.Tab.getOrCreateInstance(modal.querySelector('#portfolio-general-tab')).show();
    });
    if (errors) {
        const field = errors.dataset.portfolioErrors.startsWith('categorias')
            ? modal.querySelector('[data-area-search]')
            : modal.querySelector(`[name="${errors.dataset.portfolioErrors}"]`);
        const panel = field?.closest('.tab-pane');
        const tab = panel && modal.querySelector(`[data-bs-target="#${panel.id}"]`);
        if (tab) bootstrap.Tab.getOrCreateInstance(tab).show();
        bootstrap.Modal.getOrCreateInstance(modal).show();
    }
});
