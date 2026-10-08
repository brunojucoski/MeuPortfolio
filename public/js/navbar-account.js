document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-account-menu]');
    if (!root) return;
    const surface = root.querySelector('.account-menu-surface');
    const button = root.querySelector('[data-account-toggle]');
    const content = root.querySelector('[data-account-content]');
    const items = [...content.querySelectorAll('[role="menuitem"]')];
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let open = false;
    let sequence = 0;
    let animations = [];

    async function setOpen(next, restoreFocus = false) {
        if (open === next) return;
        const current = ++sequence;
        const previous = surface.getBoundingClientRect();
        const previousRadius = getComputedStyle(surface).borderRadius;
        const previousAvatar = getComputedStyle(button).transform;
        const previousOpacity = getComputedStyle(content).opacity;
        animations.forEach(animation => animation.cancel());
        open = next;
        root.dataset.open = String(open);
        button.setAttribute('aria-expanded', String(open));
        button.setAttribute('aria-label', open ? 'Fechar menu da conta' : 'Abrir menu da conta');
        content.hidden = false;
        content.inert = !open;
        const width = open ? Math.min(280, window.innerWidth - 24, root.getBoundingClientRect().right - 12) : 44;
        surface.style.width = `${width}px`;
        const height = open ? content.getBoundingClientRect().height + 2 : 44;
        const radius = open ? '8px' : '22px';
        const avatarTransform = open ? `translate(${-(width - 44) / 2}px, 18px) scale(1.12)` : 'translate(0, 0) scale(1)';
        surface.style.height = `${height}px`;
        surface.style.borderRadius = radius;
        button.style.transform = avatarTransform;
        content.style.opacity = open ? '1' : '0';
        if (reducedMotion.matches || !surface.animate) {
            content.hidden = !open;
        } else {
            // Morph the measured surface and avatar together; reveal the menu after expansion begins.
            const spring = { duration: 340, easing: 'cubic-bezier(.22, 1.12, .36, 1)' };
            animations = [
                surface.animate([
                    { width: `${previous.width}px`, height: `${previous.height}px`, borderRadius: previousRadius },
                    { width: `${width}px`, height: `${height}px`, borderRadius: radius },
                ], spring),
                button.animate([{ transform: previousAvatar }, { transform: avatarTransform }], spring),
                content.animate([
                    { opacity: next ? 0 : previousOpacity, filter: next ? 'blur(6px)' : 'blur(0)' },
                    { opacity: next ? 1 : 0, filter: next ? 'blur(0)' : 'blur(6px)' },
                ], { duration: next ? 220 : 140, delay: next ? 90 : 0, easing: 'ease-out', fill: 'backwards' }),
            ];
            await Promise.allSettled(animations.map(animation => animation.finished));
            if (current !== sequence) return;
            content.hidden = !open;
        }
        if (open) items[0]?.focus({ preventScroll: true });
        else if (restoreFocus && !document.querySelector('.modal.show, .offcanvas.show')) button.focus({ preventScroll: true });
    }

    button.addEventListener('click', () => setOpen(!open, true));
    button.addEventListener('keydown', event => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            if (!open) setOpen(true);
            else (event.key === 'ArrowDown' ? items[0] : items.at(-1))?.focus();
        }
    });
    root.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            event.preventDefault();
            event.stopPropagation();
            setOpen(false, true);
        }
        const index = items.indexOf(document.activeElement);
        if (index < 0) return;
        if (['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) {
            event.preventDefault();
            const next = event.key === 'Home' ? 0 : event.key === 'End' ? items.length - 1 : (index + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
            items[next].focus();
        }
    });
    root.addEventListener('click', event => {
        if (event.target.closest('[role="menuitem"], .account-menu-name')) setOpen(false);
    });
    document.addEventListener('pointerdown', event => {
        if (!root.contains(event.target)) setOpen(false);
    });
    root.addEventListener('focusout', () => {
        requestAnimationFrame(() => { if (!root.contains(document.activeElement)) setOpen(false); });
    });
    document.addEventListener('show.bs.offcanvas', () => setOpen(false));
    window.addEventListener('resize', () => setOpen(false));
});
