const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { resolve } = require('node:path');
const { runInNewContext } = require('node:vm');

function fixture(limit) {
    let submit;
    const messages = [];
    const document = {
        currentScript: { dataset: { uploadRequestMaxBytes: String(limit) } },
        addEventListener(type, listener, capture) {
            assert.equal(type, 'submit');
            assert.equal(capture, true);
            submit = listener;
        },
        createElement() { return { dataset: {}, setAttribute() {}, focus() { this.focused = true; } }; },
    };
    runInNewContext(readFileSync(resolve(__dirname, '../../public/js/upload-request-limit.js'), 'utf8'), { document });
    return {
        messages,
        submit(sizes, disabledSizes = []) {
            const fields = [...sizes.map(size => ({ files: [{ size }] })), ...disabledSizes.map(size => ({ disabled: true, files: [{ size }] }))];
            let prevented = false;
            const form = {
                querySelectorAll: () => fields,
                querySelector: () => null,
                prepend: error => messages.push(error),
            };
            submit?.({ target: form, preventDefault() { prevented = true; }, stopImmediatePropagation() {} });
            return prevented;
        },
    };
}

test('blocks aggregate uploads before form submission, not just each file', () => {
    const ui = fixture(3145728);
    assert.equal(ui.submit([1800000, 1800000]), true);
    assert.equal(ui.messages.length, 1);
    assert.match(ui.messages[0].textContent, /3\.0 MiB/);
    assert.equal(ui.messages[0].focused, true);
});

test('permits small payloads, ignores disabled file inputs and keeps local mode unrestricted', () => {
    const ui = fixture(3145728);
    assert.equal(ui.submit([100, 200], [10000000]), false);
    assert.equal(ui.messages.length, 0);
    assert.equal(fixture(0).submit([10000000]), false);
});
