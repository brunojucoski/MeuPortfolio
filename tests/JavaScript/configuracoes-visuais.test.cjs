const assert = require('node:assert/strict');
const { test } = require('node:test');
const { readFileSync } = require('node:fs');
const { resolve } = require('node:path');
const { runInNewContext } = require('node:vm');

class Element {
    constructor() { this.listeners = {}; this.dataset = {}; this.value = ''; this.files = []; this.hidden = true; }
    addEventListener(type, fn) { this.listeners[type] = fn; }
    emit(type, event = {}) { this.listeners[type]?.(event); }
    removeAttribute(name) { delete this[name]; }
}

function fixture({ logo = false, saved = false } = {}) {
    const color = new Element();
    color.value = '#6d2e2e';
    const output = new Element();
    const sample = new Element();
    sample.style = { setProperty: (name, value) => { sample[name] = value; } };
    const reset = new Element();
    reset.dataset.colorReset = '#6d2e2e';
    const input = new Element();
    const preview = new Element();
    preview.dataset = { currentSrc: '/current.png', defaultSrc: '/default.png' };
    preview.src = preview.dataset.currentSrc;
    const fallback = logo ? new Element() : null;
    if (logo) {
        preview.dataset = { currentSrc: saved ? '/logo.png' : '', defaultSrc: '' };
        if (saved) preview.src = '/logo.png';
        else preview.removeAttribute('src');
        preview.hidden = !saved;
        fallback.hidden = saved;
    }
    const clear = new Element();
    const defaults = new Element();
    defaults.checked = false;
    const error = new Element();
    const slot = { querySelector: selector => ({
        '[data-image-input]': input, '[data-image-preview]': preview, '[data-image-clear]': clear,
        '[data-image-default]': defaults, '[data-image-error]': error, '[data-image-fallback]': fallback,
    })[selector] };
    const form = {
        querySelector: selector => ({ '#cor_base': color, '[data-color-value]': output, '[data-color-sample]': sample, '[data-color-reset]': reset })[selector],
        querySelectorAll: () => [slot],
    };
    const window = new Element();
    const revoked = [];
    let next = 0;
    runInNewContext(readFileSync(resolve(__dirname, '../../public/js/configuracoes-visuais.js'), 'utf8'), {
        document: { querySelector: () => form }, window,
        URL: { createObjectURL: () => 'blob:' + ++next, revokeObjectURL: url => revoked.push(url) },
    });
    return { color, output, sample, reset, input, preview, clear, defaults, error, revoked, window, fallback };
}

test('color changes affect only the preview, with a working default reset', () => {
    const f = fixture();
    f.color.value = '#287850';
    f.color.emit('input');
    assert.equal(f.output.textContent, '#287850');
    assert.equal(f.sample['--preview-color'], '#287850');
    f.reset.emit('click');
    assert.equal(f.color.value, '#6d2e2e');
    assert.equal(f.output.textContent, '#6d2e2e');
});

test('image previews can be selected, replaced, discarded and cleaned up', () => {
    const f = fixture();
    f.input.files = [{ type: 'image/png', size: 100 }];
    f.input.emit('change');
    assert.equal(f.preview.src, 'blob:1');
    assert.equal(f.clear.hidden, false);
    f.input.emit('change');
    assert.deepEqual(f.revoked, ['blob:1']);
    f.clear.emit('click');
    assert.equal(f.preview.src, '/current.png');
    assert.equal(f.clear.hidden, true);
    assert.equal(f.input.value, '');
    assert.deepEqual(f.revoked, ['blob:1', 'blob:2']);
    f.input.emit('change');
    f.window.emit('pagehide', { persisted: true });
    assert.equal(f.revoked.at(-1), 'blob:2');
    f.window.emit('pagehide');
    assert.equal(f.revoked.at(-1), 'blob:3');
});

test('restoring defaults discards the selected file and can be undone', () => {
    const f = fixture();
    f.input.files = [{ type: 'image/jpeg', size: 100 }];
    f.input.emit('change');
    f.defaults.checked = true;
    f.defaults.emit('change');
    assert.equal(f.preview.src, '/default.png');
    assert.equal(f.clear.hidden, true);
    f.defaults.checked = false;
    f.defaults.emit('change');
    assert.equal(f.preview.src, '/current.png');
});

test('unsupported or oversized files do not become image previews', () => {
    const f = fixture();
    for (const file of [{ type: 'image/svg+xml', size: 100 }, { type: 'image/png', size: 2097153 }]) {
        f.input.files = [file];
        f.input.emit('change');
        assert.equal(f.preview.src, '/current.png');
        assert.equal(f.clear.hidden, true);
        assert.ok(f.error.textContent.includes('2 MB'));
    }
});

test('new logo previews replace the default name and discard without a broken image', () => {
    const f = fixture({ logo: true });
    assert.equal(f.fallback.hidden, false);
    f.input.files = [{ type: 'image/png', size: 100 }];
    f.input.emit('change');
    assert.equal(f.preview.hidden, false);
    assert.equal(f.fallback.hidden, true);
    assert.equal(f.preview.src, 'blob:1');
    f.clear.emit('click');
    assert.equal(f.preview.hidden, true);
    assert.equal(f.preview.src, undefined);
    assert.equal(f.fallback.hidden, false);
});

test('saved logos can revert to the default name and restore their current image', () => {
    const f = fixture({ logo: true, saved: true });
    f.defaults.checked = true;
    f.defaults.emit('change');
    assert.equal(f.preview.hidden, true);
    assert.equal(f.fallback.hidden, false);
    assert.equal(f.preview.src, undefined);
    f.defaults.checked = false;
    f.defaults.emit('change');
    assert.equal(f.preview.hidden, false);
    assert.equal(f.fallback.hidden, true);
    assert.equal(f.preview.src, '/logo.png');
});
