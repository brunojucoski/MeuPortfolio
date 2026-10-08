document.addEventListener('DOMContentLoaded', function () {
    if (typeof AOS !== 'undefined') {
        AOS.init({ duration: 750, once: true, offset: 48, easing: 'ease-out-cubic' });
    }

    const audience = document.getElementById('home-audience-switch');
    const artistPanel = document.getElementById('conteudoArtista');
    const requesterPanel = document.getElementById('conteudoContratante');
    if (audience && artistPanel && requesterPanel) {
        function updateAudience() {
            const requester = audience.checked;
            audience.closest('.home-audience-toggle').classList.toggle('is-requester', requester);
            artistPanel.hidden = requester;
            requesterPanel.hidden = !requester;
            artistPanel.classList.toggle('d-none', requester);
            requesterPanel.classList.toggle('d-none', !requester);
            if (typeof AOS !== 'undefined') AOS.refresh();
        }
        audience.addEventListener('change', updateAudience);
        updateAudience();
    }

    const aurora = document.querySelector('[data-aurora-bars]');
    if (!aurora) return;
    const bars = Array.from(aurora.querySelectorAll('.home-aurora-bar'));
    if (bars.length < 2) return;

    const drift = bars.map(() => ({
        phase: Math.random() * Math.PI * 2,
        frequency: 0.45 + Math.random() * 0.65,
    }));

    // Keep the reference's wave envelope, with smooth independent drift per bar.
    function render(time) {
        bars.forEach(function (bar, index) {
            const arch = Math.sin(index / (bars.length - 1) * Math.PI);
            const phase1 = index / bars.length * Math.PI * 2;
            const phase2 = index / bars.length * Math.PI * 5.3;
            const wave = 0.5 + 0.25 * Math.sin(time * 1.1 + phase1)
                + 0.25 * Math.sin(time * 0.7 + phase2);
            const variation = 0.5 + 0.5 * Math.sin(time * drift[index].frequency + drift[index].phase);
            const height = 0.18 + (arch * 0.55 + wave * 0.3 + variation * 0.15) * (0.92 - 0.18);
            bar.style.height = height * 100 + '%';
        });
    }

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let visible = true;
    let frame = null;
    let previous = null;
    let time = 0;

    function animate(timestamp) {
        if (previous !== null) time += Math.min(timestamp - previous, 64) / 1000 * 1.5;
        previous = timestamp;
        render(time);
        frame = window.requestAnimationFrame(animate);
    }

    function updateAnimation() {
        const active = visible && !document.hidden && !reducedMotion.matches;
        if (active && frame === null) {
            previous = null;
            frame = window.requestAnimationFrame(animate);
        } else if (!active && frame !== null) {
            window.cancelAnimationFrame(frame);
            frame = null;
            previous = null;
        }
    }

    render(0);
    document.addEventListener('visibilitychange', updateAnimation);
    reducedMotion.addEventListener('change', updateAnimation);
    if ('IntersectionObserver' in window) {
        const observer = new window.IntersectionObserver(function (entries) {
            visible = entries[0].isIntersecting;
            updateAnimation();
        });
        observer.observe(aurora);
    }
    updateAnimation();
});
