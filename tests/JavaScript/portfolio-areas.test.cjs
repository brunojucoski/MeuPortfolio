const assert = require('node:assert/strict');
const { test } = require('node:test');
const { readFileSync } = require('node:fs');
const { resolve } = require('node:path');
const { runInNewContext } = require('node:vm');

class Element {
    constructor() { this.dataset = {}; this.children = []; this.listeners = {}; this.attributes = {}; this.value = ''; this.hidden = true; this.disabled = false; }
    addEventListener(type, fn) { (this.listeners[type] ??= []).push(fn); }
    emit(type, event = {}) { return Promise.all((this.listeners[type] || []).map(fn => fn(event))); }
    append(...nodes) { this.children.push(...nodes); }
    replaceChildren(...nodes) { this.children = nodes; }
    setAttribute(name, value) { this.attributes[name] = value; if (name === 'value') this.value = value; }
    removeAttribute(name) { delete this.attributes[name]; }
    focus() { this.focused = true; }
    scrollIntoView() {}
    closest() { return this; }
}

function fixture() {
    const root = new Element(), search = new Element(), results = new Element(), status = new Element(), chips = new Element();
    const toggle = new Element(), form = new Element(), name = new Element(), error = new Element(), submit = new Element(), cancel = new Element();
    const settings = new Element();
    const initial = new Element();
    initial.dataset = { areaId: '1', areaName: 'Pintura' };
    chips.querySelectorAll = () => [initial];
    form.querySelector = () => name;
    root.dataset = { searchUrl: 'http://local.test/areas/buscar', createUrl: 'http://local.test/areas' };
    root.contains = node => !!node;
    root.querySelector = selector => ({
        '[data-area-search]': search, '[data-area-results]': results, '[data-area-status]': status,
        '[data-area-selected]': chips, '[data-area-create-toggle]': toggle, '[data-area-create]': form,
        '[data-area-error]': error, '[data-area-create-submit]': submit, '[data-area-create-cancel]': cancel,
    })[selector];
    let ready, now = 0, next = 0;
    const timers = new Map(), requests = [];
    runInNewContext(readFileSync(resolve(__dirname, '../../public/js/portfolio-areas.js'), 'utf8'), {
        document: {
            addEventListener: (_, fn) => { ready = fn; },
            querySelector: selector => selector.startsWith('meta') ? { content: 'test-csrf' } : root,
            getElementById: () => settings, createElement: () => new Element(),
        },
        window: { location: { href: 'http://local.test/perfil' } }, URL, AbortController,
        setTimeout: (fn, delay) => { const id = ++next; timers.set(id, { fn, at: now + delay }); return id; },
        clearTimeout: id => timers.delete(id),
        fetch: (url, options) => new Promise((resolve, reject) => requests.push({ url: String(url), options, resolve, reject })),
    });
    ready();
    return {
        root, search, results, status, chips, toggle, form, name, error, submit, cancel, settings, requests,
        type(text) { search.value = text; search.emit('input'); },
        advance(ms = 250) {
            now += ms;
            for (const [id, timer] of [...timers]) if (timer.at <= now) { timers.delete(id); timer.fn(); }
        },
        async respond(index, body, status = 200) {
            requests[index].resolve({ ok: status < 400, status, json: async () => body });
            for (let i = 0; i < 5; i++) await Promise.resolve();
        },
        values() { return chips.children.map(chip => chip.children[0].value); },
        key(key) { let prevented = false; search.emit('keydown', { key, preventDefault() { prevented = true; }, stopPropagation() {} }); return prevented; },
    };
}

test('only two-character searches make debounced requests and exclude selected areas', async () => {
    const f = fixture();
    assert.deepEqual(f.values(), ['1']);
    f.type('p'); f.advance();
    assert.equal(f.requests.length, 0);
    assert.equal(f.toggle.disabled, true);
    f.type('pi'); f.advance(249);
    assert.equal(f.requests.length, 0);
    f.advance(1);
    assert.ok(f.requests[0].url.endsWith('?q=pi'));
    await f.respond(0, { data: [{ id: 1, nome: 'Pintura' }, { id: 2, nome: 'Pintura digital' }], more: false });
    assert.equal(f.results.children.length, 1);
    assert.equal(f.results.children[0].textContent, 'Pintura digital');
    assert.equal(f.search.attributes['aria-expanded'], 'true');
});

test('keyboard selection and chip removal keep real form-associated input values', async () => {
    const f = fixture();
    f.type('gr'); f.advance();
    await f.respond(0, { data: [{ id: 3, nome: 'Gravura' }], more: false });
    assert.equal(f.key('ArrowDown'), true);
    assert.equal(f.search.attributes['aria-activedescendant'], 'portfolio-area-option-0');
    assert.equal(f.key('Enter'), true);
    assert.deepEqual(f.values(), ['1', '3']);
    assert.equal(f.chips.children[1].children[0].attributes.form, 'portfolioSettingsForm');
    assert.equal(f.chips.children[1].children[0].name, 'categorias[]');
    f.chips.emit('click', { target: f.chips.children[0].children[2] });
    assert.deepEqual(f.values(), ['3']);
    assert.equal(f.search.focused, true);
    assert.equal(f.results.hidden, true);
});

test('stale requests cannot replace newer searches or reopen cleared results', async () => {
    const f = fixture();
    f.type('pi'); f.advance();
    f.type('gr'); f.advance();
    assert.equal(f.requests[0].options.signal.aborted, true);
    await f.respond(1, { data: [{ id: 3, nome: 'Gravura' }] });
    await f.respond(0, { data: [{ id: 2, nome: 'Pintura digital' }] });
    assert.equal(f.results.children[0].textContent, 'Gravura');
    f.type('fo'); f.advance();
    f.type('');
    await f.respond(2, { data: [{ id: 4, nome: 'Fotografia' }] });
    assert.equal(f.results.hidden, true);
    assert.equal(f.search.attributes['aria-expanded'], 'false');
});

test('creating an area is explicit, blocks premature save, and selects the returned record safely', async () => {
    const f = fixture();
    f.type('Gravura');
    f.toggle.emit('click');
    assert.equal(f.form.hidden, false);
    assert.equal(f.name.value, 'Gravura');
    const creating = f.form.emit('submit', { preventDefault() {} });
    assert.equal(f.submit.disabled, true);
    let prevented = false;
    await f.settings.emit('submit', { preventDefault() { prevented = true; } });
    assert.equal(prevented, true);
    assert.deepEqual(JSON.parse(f.requests[0].options.body), { nome: 'Gravura', confirmacao: true });
    assert.equal(f.requests[0].options.headers['X-CSRF-TOKEN'], 'test-csrf');
    await f.respond(0, { data: { id: 3, nome: '<b>Gravura</b>' } }, 201);
    await creating;
    assert.deepEqual(f.values(), ['1', '3']);
    assert.equal(f.chips.children[1].children[1].textContent, '<b>Gravura</b>');
    assert.equal(f.chips.children[1].children[1].innerHTML, undefined);
    assert.equal(f.form.hidden, true);
    assert.equal(f.submit.disabled, false);
});

test('validation errors preserve selection and reusing an existing area does not duplicate it', async () => {
    const f = fixture();
    f.type('Pintura'); f.toggle.emit('click');
    const failing = f.form.emit('submit', { preventDefault() {} });
    await f.respond(0, { errors: { nome: ['Nome inválido.'] } }, 422);
    await failing;
    assert.equal(f.error.textContent, 'Nome inválido.');
    assert.equal(f.form.hidden, false);
    assert.deepEqual(f.values(), ['1']);
    const retry = f.form.emit('submit', { preventDefault() {} });
    await f.respond(1, { data: { id: 1, nome: 'Pintura' } });
    await retry;
    assert.deepEqual(f.values(), ['1']);
});

test('cancelling settings restores initial selections and ignores pending creation results', async () => {
    const f = fixture();
    f.chips.emit('click', { target: f.chips.children[0].children[2] });
    assert.deepEqual(f.values(), []);
    f.type('Gravura'); f.toggle.emit('click');
    const pending = f.form.emit('submit', { preventDefault() {} });
    await f.settings.emit('reset');
    await f.respond(0, { data: { id: 3, nome: 'Gravura' } }, 201);
    await pending;
    assert.deepEqual(f.values(), ['1']);
    assert.equal(f.form.hidden, true);
    assert.equal(f.results.hidden, true);
});
