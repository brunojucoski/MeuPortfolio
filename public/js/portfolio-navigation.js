document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('portfolio-navigation');
    if (!root) return;
    const categories = root.querySelector('#portfolio-categorias');
    const postsView = root.querySelector('#portfolio-posts');
    const links = [...root.querySelectorAll('[data-portfolio-category]')];
    const posts = [...root.querySelectorAll('[data-portfolio-post]')];
    const title = root.querySelector('[data-portfolio-title]');
    const titleText = root.querySelector('[data-portfolio-title-text]');
    const titleIcon = root.querySelector('[data-portfolio-title-icon]');
    const description = root.querySelector('[data-portfolio-description]');
    const back = root.querySelector('[data-portfolio-back]');
    const empty = root.querySelector('[data-portfolio-empty]');
    const status = root.querySelector('[data-portfolio-status]');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let active = root.dataset.activeCategory;
    let sequence = 0;
    let scrollFrame = 0;
    let animation;

    const postModal = document.getElementById('postModal');
    const postCategory = postModal?.querySelector('#post_modal_id_categoria');
    const postCategoryLabel = postModal?.querySelector('[data-post-category-label]');
    postModal?.addEventListener('show.bs.modal', event => {
        if (!postCategory || !postCategoryLabel) return;
        const fromPortfolio = event.relatedTarget?.matches('[data-portfolio-new-post]');
        const link = fromPortfolio && links.find(item => item.dataset.portfolioCategory === root.dataset.activeCategory);
        postCategory.value = link ? link.dataset.portfolioCategory : '';
        postCategoryLabel.textContent = link ? link.dataset.categoryName : 'Página principal (sem álbum)';
    });

    const stopScroll = () => cancelAnimationFrame(scrollFrame);
    window.addEventListener('wheel', stopScroll, { passive: true });
    window.addEventListener('touchstart', stopScroll, { passive: true });
    window.addEventListener('keydown', stopScroll);

    function scrollToPanel(panel) {
        stopScroll();
        const navbar = document.querySelector('.navbar.fixed-top');
        const offset = (navbar?.getBoundingClientRect().height || 0) + 20;
        const start = window.scrollY;
        const target = Math.max(0, panel.getBoundingClientRect().top + start - offset);
        if (reducedMotion.matches) {
            window.scrollTo(0, target);
            return;
        }
        const began = performance.now();
        const step = now => {
            const progress = Math.min(1, (now - began) / 480);
            const ease = progress < 0.5 ? 4 * progress ** 3 : 1 - (-2 * progress + 2) ** 3 / 2;
            window.scrollTo(0, start + (target - start) * ease);
            if (progress < 1) scrollFrame = requestAnimationFrame(step);
        };
        scrollFrame = requestAnimationFrame(step);
    }

    async function slide(panel, entering, direction) {
        if (!panel || panel.hidden || reducedMotion.matches || !panel.animate) return;
        const distance = direction * 44;
        animation = panel.animate(entering
            ? [{ opacity: 0, transform: `translateX(${distance}px)` }, { opacity: 1, transform: 'translateX(0)' }]
            : [{ opacity: 1, transform: 'translateX(0)' }, { opacity: 0, transform: `translateX(${-distance}px)` }],
        { duration: entering ? 300 : 180, easing: entering ? 'ease-out' : 'ease-in' });
        try { await animation.finished; } catch { /* A newer selection cancels this transition. */ }
    }

    async function selectCategory(id, pushHistory = true) {
        const link = links.find(item => item.dataset.portfolioCategory === id);
        if (id && !link) return;
        const currentSequence = ++sequence;
        animation?.cancel();
        stopScroll();
        root.setAttribute('aria-busy', 'true');
        const forward = id !== '';
        const previouslyActive = active;
        await slide(active ? postsView : categories, false, forward ? 1 : -1);
        if (currentSequence !== sequence) return;

        let total = 0;
        posts.forEach(post => {
            post.hidden = post.dataset.postCategory !== id;
            if (!post.hidden) total++;
            post.querySelectorAll('.carousel').forEach(el => {
                const carousel = window.bootstrap?.Carousel.getInstance(el);
                if (post.hidden) carousel?.pause();
                else carousel?.cycle();
            });
        });
        if (categories) categories.hidden = forward;
        postsView.hidden = !forward && total === 0;
        back.hidden = !forward;
        titleText.textContent = link?.dataset.categoryName || 'Posts';
        titleIcon.replaceChildren();
        const icon = link?.querySelector('.perfil-categoria-title .portfolio-icon');
        if (icon) titleIcon.append(icon.cloneNode(true));
        description.textContent = link?.dataset.categoryDescription || '';
        description.hidden = !description.textContent;
        empty.hidden = total > 0;
        status.textContent = `${titleText.textContent}: ${total} ${total === 1 ? 'post' : 'posts'}.`;
        links.forEach(item => {
            if (item === link) item.setAttribute('aria-current', 'true');
            else item.removeAttribute('aria-current');
        });
        active = id;
        root.dataset.activeCategory = id;

        if (pushHistory) {
            const url = new URL(window.location.href);
            if (id) url.searchParams.set('categoria', id);
            else url.searchParams.delete('categoria');
            url.hash = forward ? 'portfolio-posts' : 'portfolio-categorias';
            history.pushState(null, '', url);
        }
        const target = forward ? postsView : (categories || postsView);
        scrollToPanel(target);
        await slide(target, true, forward ? 1 : -1);
        if (currentSequence !== sequence) return;
        root.removeAttribute('aria-busy');
        if (forward) title.focus({ preventScroll: true });
        else (links.find(item => item.dataset.portfolioCategory === previouslyActive) || links[0])?.focus({ preventScroll: true });
    }

    root.addEventListener('click', event => {
        const link = event.target.closest('[data-portfolio-category], [data-portfolio-back]');
        if (!link || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        selectCategory(link.dataset.portfolioCategory || '');
    });
    window.addEventListener('popstate', () => selectCategory(new URL(window.location.href).searchParams.get('categoria') || '', false));
    if (active) requestAnimationFrame(() => scrollToPanel(postsView));
});
