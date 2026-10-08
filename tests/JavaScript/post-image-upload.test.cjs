const assert = require('node:assert/strict');
const { test } = require('node:test');
const { readFileSync } = require('node:fs');
const { resolve } = require('node:path');
const { runInNewContext } = require('node:vm');

class Element {
    constructor() {
        this.children = [];
        this.dataset = {};
        this.listeners = {};
        this.textContent = '';
        this.classes = new Set();
        this.classList = { add: value => this.classes.add(value), remove: value => this.classes.delete(value) };
    }
    addEventListener(type, callback) { (this.listeners[type] ??= []).push(callback); }
    emit(type, event = {}) { (this.listeners[type] || []).forEach(callback => callback(event)); }
    append(...nodes) { this.children.push(...nodes); }
    replaceChildren(...nodes) { this.children = nodes; }
    setAttribute(name, value) { this[name] = value; }
    focus() { this.focused = true; }
    querySelectorAll() { return this.children.flatMap(tile => tile.children.filter(child => child.dataset.removeImage)); }
}

function fixture() {
    const root = new Element();
    const input = new Element();
    const dropzone = new Element();
    const previews = new Element();
    const error = new Element();
    const status = new Element();
    const form = new Element();
    input.form = form;
    input.files = [];
    root.dataset = { maxFiles: '20', maxSize: '8388608', maxTotal: '66060288' };
    root.querySelector = selector => ({
        'input[type="file"]': input, '.post-image-dropzone': dropzone,
        '[data-upload-previews]': previews, '[data-upload-error]': error, '[data-upload-status]': status,
    })[selector];
    let ready;
    const document = {
        addEventListener: (type, callback) => { ready = callback; },
        querySelectorAll: () => [root], createElement: () => new Element(),
    };
    const revoked = [];
    let urls = 0;
    class DataTransfer {
        constructor() { this.files = []; this.items = { add: file => this.files.push(file) }; }
    }
    runInNewContext(readFileSync(resolve(__dirname, '../../public/js/post-image-upload.js'), 'utf8'), {
        document, DataTransfer, queueMicrotask, window: new Element(),
        URL: { createObjectURL: () => `blob:${++urls}`, revokeObjectURL: url => revoked.push(url) },
    });
    ready();
    return {
        root, input, previews, error, status, revoked, form,
        select(files) { input.files = files; input.emit('change'); },
        drop(files) {
            let prevented = false;
            dropzone.emit('dragenter', { preventDefault() {} });
            assert.equal(dropzone.classes.has('is-dragging'), true);
            dropzone.emit('drop', { preventDefault() { prevented = true; }, dataTransfer: { files } });
            assert.equal(prevented, true);
            assert.equal(dropzone.classes.has('is-dragging'), false);
        },
        remove(index) {
            const button = previews.querySelectorAll()[index];
            previews.emit('click', { target: { closest: () => button } });
        },
    };
}

const file = (name, size = 100, type = 'image/png') => ({ name, size, type, lastModified: 1 });

test('selections accumulate without duplicates and removals update the multipart input', () => {
    const ui = fixture();
    const first = file('first.png');
    const second = file('second.png');
    ui.select([first]);
    ui.select([first, second]);
    assert.deepEqual(ui.input.files.map(item => item.name), ['first.png', 'second.png']);
    assert.equal(ui.previews.children.length, 2);
    ui.remove(0);
    assert.deepEqual(ui.input.files.map(item => item.name), ['second.png']);
    assert.equal(ui.previews.children.length, 1);
    assert.deepEqual(ui.revoked, ['blob:1']);
    ui.remove(0);
    assert.equal(ui.input.files.length, 0);
    assert.equal(ui.previews.children.length, 0);
    assert.equal(ui.input.focused, true);
});

test('drop accepts images and rejects invalid or oversized files without losing existing selection', () => {
    const ui = fixture();
    ui.select([file('original.png')]);
    ui.drop([file('new.gif', 100, 'image/gif'), file('bad.txt', 100, 'text/plain'), file('huge.png', 8388609)]);
    assert.deepEqual(ui.input.files.map(item => item.name), ['original.png', 'new.gif']);
    assert.equal(ui.error.hidden, false);
    assert.match(ui.error.textContent, /bad.txt/);
    assert.match(ui.error.textContent, /huge.png/);
    assert.match(ui.status.textContent, /2 imagens selecionadas/);
});

test('count and total size limits do not silently include rejected files', () => {
    const ui = fixture();
    ui.drop(Array.from({ length: 21 }, (_, index) => file(`${index}.png`)));
    assert.equal(ui.input.files.length, 20);
    assert.match(ui.error.textContent, /20 imagens/);
    ui.root.emit('post-images-reset');
    ui.drop(Array.from({ length: 8 }, (_, index) => file(`${index}.png`, 8388608)));
    assert.equal(ui.input.files.length, 7);
    assert.match(ui.error.textContent, /limite de envio/);
});

test('resetting a form or opening another post clears selection and releases object URLs', async () => {
    const ui = fixture();
    ui.select([file('first.png')]);
    ui.form.emit('reset');
    await Promise.resolve();
    assert.equal(ui.input.files.length, 0);
    assert.deepEqual(ui.revoked, ['blob:1']);
    ui.select([file('second.png')]);
    ui.root.emit('post-images-reset');
    assert.equal(ui.previews.children.length, 0);
    assert.equal(ui.error.hidden, true);
    assert.deepEqual(ui.revoked, ['blob:1', 'blob:2']);
});
