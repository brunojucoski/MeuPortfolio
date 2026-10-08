document.addEventListener('DOMContentLoaded', () => {
    const normalize = text => text.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    document.querySelectorAll('[data-icon-picker]').forEach(picker => {
        const options = [...picker.querySelectorAll('[data-icon-option]')];
        const search = picker.querySelector('[data-icon-search]');
        const filters = [...picker.querySelectorAll('[data-icon-filter]')];
        const selected = picker.querySelector('[data-icon-selected]');
        const empty = picker.querySelector('[data-icon-empty]');
        let group = '';

        const filter = () => {
            const term = normalize(search.value);
            let total = 0;
            options.forEach(option => {
                const matchesGroup = !group || !option.dataset.iconGroup || option.dataset.iconGroup === group;
                option.hidden = !matchesGroup || !normalize(`${option.dataset.iconName} ${option.dataset.iconGroup}`).includes(term);
                if (!option.hidden) total++;
            });
            empty.hidden = total > 0;
        };
        const sync = () => {
            const option = picker.querySelector('input:checked')?.closest('[data-icon-option]');
            selected.textContent = option ? option.title : 'Sem ícone';
            search.value = '';
            group = option?.dataset.iconGroup || '';
            filters.forEach(button => {
                const active = button.dataset.iconFilter === group;
                button.classList.toggle('is-active', active);
                button.setAttribute('aria-pressed', String(active));
            });
            filter();
        };
        filters.forEach(button => button.addEventListener('click', () => {
            group = button.dataset.iconFilter;
            filters.forEach(item => {
                const active = item === button;
                item.classList.toggle('is-active', active);
                item.setAttribute('aria-pressed', String(active));
            });
            filter();
        }));
        search.addEventListener('input', filter);
        picker.addEventListener('change', () => {
            selected.textContent = picker.querySelector('input:checked')?.closest('[data-icon-option]').title || 'Sem ícone';
        });
        picker.addEventListener('icon-picker-sync', sync);
        sync();
    });
});
