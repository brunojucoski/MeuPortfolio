const assert = require('node:assert/strict');
const { test } = require('node:test');
const { readFileSync } = require('node:fs');
const { resolve } = require('node:path');
const { runInNewContext } = require('node:vm');

function fixture({ reduced = false, width = 100, height = 100, supported = true } = {}) {
    const listeners = {};
    const documentListeners = {};
    const frames = new Map();
    let next = 0;
    let now = 0;
    let motionChange;
    const motion = { matches: reduced, addEventListener: (_, handler) => { motionChange = handler; } };
    const draws = [];
    let transforms = [];
    const context = {
        clearRect() { draws.length = 0; },
        setTransform(...args) { transforms = args; },
        fillRect(x, y, w, h) { draws.push({ x, y, w, h, alpha: this.globalAlpha }); },
    };
    const canvas = { getContext: () => supported ? context : null };
    const document = {
        hidden: false, querySelector: () => canvas,
        addEventListener: (type, handler) => { documentListeners[type] = handler; },
    };
    const window = {
        innerWidth: width, innerHeight: height, devicePixelRatio: 3,
        matchMedia: () => motion,
        addEventListener(type, handler, options) {
            assert.equal(options.passive, true);
            listeners[type] = handler;
        },
        requestAnimationFrame(handler) { frames.set(++next, handler); return next; },
        cancelAnimationFrame: id => frames.delete(id),
    };
    runInNewContext(readFileSync(resolve(__dirname, '../../public/js/auth-pixel-background.js'), 'utf8'), {
        document, window, performance: { now: () => now },
    });
    return {
        canvas, document, window, frames, draws,
        get transforms() { return transforms; },
        event(type, data = {}, time = now) { now = time; listeners[type]?.(data); },
        visibility(hidden) { document.hidden = hidden; documentListeners.visibilitychange(); },
        reduce(value) { motion.matches = value; motionChange(); },
        frame(time) {
            now = time;
            const [id, handler] = [...frames.entries()][0];
            frames.delete(id);
            handler(time);
        },
    };
}

test('pixels draw, ripple and stop requesting frames when idle', () => {
    const f = fixture();
    assert.equal(f.draws.length, 400);
    assert.equal(f.canvas.width, 150);
    assert.equal(f.transforms[0], 1.5);
    f.frame(80);
    assert.ok(f.draws.some(pixel => pixel.w > 0.7));
    f.frame(2500);
    assert.equal(f.frames.size, 0);
    assert.ok(f.draws.every(pixel => pixel.w === 0.7));
});

test('pointer, touch and scroll restart waves without capturing input or duplicating frames', () => {
    const f = fixture();
    f.frame(2500);
    f.event('pointermove', { clientX: 20, clientY: 30 }, 2600);
    assert.equal(f.frames.size, 1);
    f.frame(2700);
    assert.ok(f.draws.some(pixel => pixel.alpha > 0.12));
    f.event('pointermove', { clientX: 21, clientY: 31 }, 2750);
    f.event('pointerdown', { clientX: 70, clientY: 80 }, 2800);
    f.event('scroll', {}, 2900);
    assert.equal(f.frames.size, 1);
    f.frame(5500);
    assert.equal(f.frames.size, 0);
    f.event('scroll', {}, 5800);
    assert.equal(f.frames.size, 1);
});

test('reduced motion and hidden documents disable ripples, including preference changes', () => {
    const f = fixture({ reduced: true });
    assert.equal(f.frames.size, 0);
    f.event('pointerdown', { clientX: 10, clientY: 10 });
    assert.equal(f.frames.size, 0);
    f.reduce(false);
    f.event('scroll', {}, 1000);
    assert.equal(f.frames.size, 1);
    f.visibility(true);
    assert.equal(f.frames.size, 0);
    f.event('scroll', {}, 1300);
    assert.equal(f.frames.size, 0);
    f.visibility(false);
    f.event('pointerdown', { clientX: 10, clientY: 10 });
    f.reduce(true);
    assert.equal(f.frames.size, 0);
});

test('resize clears old ripple distances and bounds the high-resolution grid', () => {
    const f = fixture({ width: 3840, height: 2160 });
    assert.ok(f.draws.length <= 40000);
    f.window.innerWidth = 320;
    f.window.innerHeight = 700;
    f.event('resize');
    assert.equal(f.canvas.width, 480);
    assert.equal(f.frames.size, 0);
    assert.ok(f.draws.every(pixel => pixel.x < 320 && pixel.y < 700));
    f.event('pointerdown', { clientX: 150, clientY: 300 }, 1000);
    f.frame(1050);
    assert.ok(f.draws.every(pixel => Number.isFinite(pixel.w)));
});

test('missing Canvas support leaves forms usable without animation errors', () => {
    const f = fixture({ supported: false });
    assert.equal(f.frames.size, 0);
});
