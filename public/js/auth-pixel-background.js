(function () {
    const canvas = document.querySelector('[data-auth-pixels]');
    if (!canvas) return;
    const context = canvas.getContext('2d');
    if (!context) return;

    const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const palette = ['#d4d4d4', '#bdbdbd', '#a3a3a3'];
    const lifetime = 2400;
    let pixels = [];
    let waves = [];
    let frame = null;
    let previousDraw = -Infinity;
    let previousPointer = -Infinity;
    let previousScroll = -Infinity;
    let pointer = null;
    let width = 0;
    let height = 0;

    function draw(timestamp) {
        context.clearRect(0, 0, width, height);
        waves = waves.filter(wave => timestamp - wave.start < lifetime);
        palette.forEach(function (color, group) {
            context.fillStyle = color;
            for (let index = group; index < pixels.length; index += palette.length) {
                const pixel = pixels[index];
                let intensity = 0;
                waves.forEach(function (wave) {
                    const age = (timestamp - wave.start) / lifetime;
                    const ring = Math.max(0, 1 - Math.abs(wave.distances[index] - age * 1500) / 150);
                    intensity = Math.max(intensity, ring * (1 - age));
                });
                const shimmer = 0.65 + 0.35 * Math.sin(timestamp * 0.006 + pixel.phase);
                const size = 0.7 + intensity * (1.3 + pixel.size * shimmer);
                context.globalAlpha = 0.12 + intensity * 0.8;
                context.fillRect(pixel.x - size / 2, pixel.y - size / 2, size, size);
            }
        });
        context.globalAlpha = 1;
    }

    function animate(timestamp) {
        frame = null;
        if (document.hidden || motion.matches) return;
        if (timestamp - previousDraw >= 1000 / 30) {
            draw(timestamp);
            previousDraw = timestamp;
        }
        if (waves.length) frame = window.requestAnimationFrame(animate);
    }

    function pulse(x, y, timestamp = performance.now()) {
        if (motion.matches || document.hidden) return;
        // Cache distances once per ripple; keep the per-frame particle loop inexpensive.
        const distances = new Float32Array(pixels.length);
        pixels.forEach((pixel, index) => {
            distances[index] = Math.hypot(pixel.x - x, pixel.y - y);
        });
        waves.push({ start: timestamp, distances });
        waves = waves.slice(-3);
        if (frame === null) frame = window.requestAnimationFrame(animate);
    }

    function stop() {
        if (frame !== null) window.cancelAnimationFrame(frame);
        frame = null;
        waves = [];
        previousDraw = -Infinity;
        draw(performance.now());
    }

    function resize() {
        width = window.innerWidth;
        height = window.innerHeight;
        const ratio = Math.min(window.devicePixelRatio || 1, 1.5);
        canvas.width = Math.round(width * ratio);
        canvas.height = Math.round(height * ratio);
        context.setTransform(ratio, 0, 0, ratio, 0, 0);
        // A viewport-sized surface and bounded grid avoid scaling with long forms.
        const gap = Math.max(5, Math.ceil(Math.sqrt(width * height / 40000)));
        pixels = [];
        for (let y = gap / 2; y < height; y += gap) {
            for (let x = gap / 2; x < width; x += gap) {
                pixels.push({ x, y, size: Math.random() * 1.7, phase: Math.random() * Math.PI * 2 });
            }
        }
        pointer = null;
        stop();
    }

    window.addEventListener('pointermove', function (event) {
        const now = performance.now();
        const moved = !pointer || Math.hypot(event.clientX - pointer.x, event.clientY - pointer.y) > 24;
        if (moved && now - previousPointer > 120) {
            pointer = { x: event.clientX, y: event.clientY };
            previousPointer = now;
            pulse(pointer.x, pointer.y, now);
        }
    }, { passive: true });
    window.addEventListener('pointerdown', function (event) {
        pointer = { x: event.clientX, y: event.clientY };
        pulse(pointer.x, pointer.y);
    }, { passive: true });
    window.addEventListener('scroll', function () {
        const now = performance.now();
        if (now - previousScroll > 160) {
            previousScroll = now;
            pulse(pointer ? pointer.x : width / 2, pointer ? pointer.y : height / 2, now);
        }
    }, { passive: true });
    window.addEventListener('resize', resize, { passive: true });
    document.addEventListener('visibilitychange', stop);
    motion.addEventListener('change', stop);

    resize();
    pulse(width / 2, height / 2);
})();
