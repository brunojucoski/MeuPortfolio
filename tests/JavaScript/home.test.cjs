const assert = require('node:assert/strict');
const { test } = require('node:test');
const { readFileSync } = require('node:fs');
const { resolve } = require('node:path');
const { runInNewContext } = require('node:vm');

class Element {
    constructor() {
        this.listeners = {};
        this.style = {};
        this.classes = new Set();
        this.classList = { toggle: (name, active) => active ? this.classes.add(name) : this.classes.delete(name) };
    }
    addEventListener(type, handler) { this.listeners[type] = handler; }
    emit(type) { this.listeners[type]?.(); }
}

function fixture({ reduced = false, background = true } = {}) {
    const audience = new Element();
    const label = new Element();
    const artist = new Element();
    const requester = new Element();
    audience.closest = () => label;
    audience.checked = false;
    const bars = Array.from({ length: 16 }, () => new Element());
    const aurora = { querySelectorAll: () => bars };
    const document = new Element();
    document.getElementById = id => ({
        'home-audience-switch': audience, conteudoArtista: artist, conteudoContratante: requester,
    })[id];
    document.querySelector = () => background ? aurora : null;
    const motion = new Element();
    motion.matches = reduced;
    let observer;
    let next = 0;
    const frames = new Map();
    const window = {
        matchMedia: () => motion,
        requestAnimationFrame: handler => { frames.set(++next, handler); return next; },
        cancelAnimationFrame: id => frames.delete(id),
        IntersectionObserver: class {
            constructor(handler) { observer = handler; }
            observe(element) { assert.equal(element, aurora); }
        },
    };
    runInNewContext(readFileSync(resolve(__dirname, '../../public/js/home.js'), 'utf8'), { document, window });
    document.emit('DOMContentLoaded');
    return {
        audience, label, artist, requester, bars, document, motion, frames,
        intersect(visible) { observer([{ isIntersecting: visible }]); },
        frame(time) {
            const [id, handler] = [...frames.entries()][0];
            frames.delete(id);
            handler(time);
        },
    };
}

test('switch alternates both panels repeatedly without reloading', () => {
    const f = fixture({ background: false });
    for (const selected of [false, true, false, true]) {
        f.audience.checked = selected;
        f.audience.emit('change');
        assert.equal(f.artist.hidden, selected);
        assert.equal(f.requester.hidden, !selected);
        assert.equal(f.artist.classes.has('d-none'), selected);
        assert.equal(f.requester.classes.has('d-none'), !selected);
        assert.equal(f.label.classes.has('is-requester'), selected);
    }
});

test('tuned aurora generates 16 bounded bars and smooth independent movement', () => {
    const f = fixture();
    const before = f.bars.map(bar => bar.style.height);
    assert.equal(f.bars.length, 16);
    before.forEach(value => assert.ok(parseFloat(value) >= 18 && parseFloat(value) <= 92));
    f.frame(0);
    f.frame(16);
    assert.notDeepEqual(f.bars.map(bar => bar.style.height), before);
    assert.equal(f.frames.size, 1);
    for (let timestamp = 32; timestamp < 20000; timestamp += 64) {
        const previous = f.bars.map(bar => parseFloat(bar.style.height));
        f.frame(timestamp);
        f.bars.forEach((bar, index) => {
            const height = parseFloat(bar.style.height);
            assert.ok(height >= 18 && height <= 92);
            assert.ok(Math.abs(height - previous[index]) < 4, 'no abrupt height jumps');
        });
    }
});

test('animation stops outside the hero and on hidden documents, resuming without duplicate frames', () => {
    const f = fixture();
    f.intersect(false);
    assert.equal(f.frames.size, 0);
    f.intersect(true);
    f.intersect(true);
    assert.equal(f.frames.size, 1);
    f.document.hidden = true;
    f.document.emit('visibilitychange');
    assert.equal(f.frames.size, 0);
    f.document.hidden = false;
    f.document.emit('visibilitychange');
    assert.equal(f.frames.size, 1);
});

test('reduced motion keeps a static aurora and responds to live preference changes', () => {
    const f = fixture({ reduced: true });
    assert.equal(f.frames.size, 0);
    assert.ok(f.bars.every(bar => bar.style.height));
    f.motion.matches = false;
    f.motion.emit('change');
    assert.equal(f.frames.size, 1);
    f.motion.matches = true;
    f.motion.emit('change');
    assert.equal(f.frames.size, 0);
});
