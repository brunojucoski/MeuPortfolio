const assert = require('node:assert/strict');
const { test } = require('node:test');
const { readFileSync } = require('node:fs');
const { resolve } = require('node:path');
const { runInNewContext } = require('node:vm');

class Element {
  constructor(value = '') { this.value = value; this.events = {}; this.classes = new Set(); }
  addEventListener(event, handler) { this.events[event] = handler; }
  emit(event) { this.events[event]?.(); }
  classList = { toggle: (name, selected) => selected ? this.classes.add(name) : this.classes.delete(name) };
}

function fixture({ requester = false, mask = true } = {}) {
  const form = new Element();
  const profile = new Element(requester ? 'solicitante' : 'artista');
  profile.options = [
    { dataset: { title: 'Cadastro de artista', image: '/artista.png', imageAlt: 'Artista' } },
    { dataset: { title: 'Cadastro de solicitante', image: '/solicitante.png', imageAlt: 'Solicitante' } },
  ];
  const toggle = new Element();
  const selector = new Element();
  const fallback = new Element();
  const title = new Element();
  const image = new Element();
  const doc = new Element();
  const phone = new Element();
  const name = new Element('Nome preenchido');
  const latitude = new Element('-28.67');
  const longitude = new Element('-49.36');
  const document = new Element();
  document.getElementById = id => ({
    'form-cadastro': form, 'cadastro-tipo': profile, 'cadastro-profile-switch': toggle,
    'cadastro-profile-toggle': selector, 'cadastro-profile-fallback': fallback,
    'cadastro-titulo': title, 'cadastro-ilustracao': image, documento: doc, telefone: phone,
  })[id];
  const window = new Element();
  if (mask) window.IMask = () => ({ get unmaskedValue() { return phone.value.replace(/\D/g, ''); } });
  runInNewContext(readFileSync(resolve(__dirname, '../../public/js/cadastro.js'), 'utf8'), { document, window });
  document.emit('DOMContentLoaded');
  return { form, profile, toggle, selector, fallback, title, image, doc, phone, name, latitude, longitude, window };
}

test('server profile presets initialize the lever, submitted value, title and configured illustration', () => {
  for (const requester of [false, true]) {
    const f = fixture({ requester });
    assert.equal(f.toggle.checked, requester);
    assert.equal(f.profile.value, requester ? 'solicitante' : 'artista');
    assert.equal(f.image.src, requester ? '/solicitante.png' : '/artista.png');
    assert.equal(f.selector.hidden, false);
    assert.equal(f.fallback.hidden, true);
    assert.equal(f.selector.classes.has('is-requester'), requester);
  }
});

test('repeated switching changes the actual submitted profile without resetting fields or coordinates', () => {
  const f = fixture();
  f.doc.value = '123.456.789-01';
  f.phone.value = '(48) 99999-9999';
  for (const requester of [true, false, true, false, true]) {
    f.toggle.checked = requester;
    f.toggle.emit('change');
    assert.equal(f.profile.value, requester ? 'solicitante' : 'artista');
    assert.equal(f.title.textContent, requester ? 'Cadastro de solicitante' : 'Cadastro de artista');
    assert.equal(f.image.src, requester ? '/solicitante.png' : '/artista.png');
    assert.equal(f.doc.value, '123.456.789-01');
    assert.equal(f.phone.value, '(48) 99999-9999');
    assert.equal(f.latitude.value, '-28.67');
    assert.equal(f.longitude.value, '-49.36');
    assert.equal(f.name.value, 'Nome preenchido');
  }
  f.form.emit('submit');
  assert.equal(f.profile.value, 'solicitante');
  assert.equal(f.doc.value, '12345678901');
  assert.equal(f.phone.value, '48999999999');
});

test('browser-restored profile remains synchronized with the visual switch', () => {
  const f = fixture();
  f.profile.value = 'solicitante';
  f.window.emit('pageshow');
  assert.equal(f.toggle.checked, true);
  assert.equal(f.title.textContent, 'Cadastro de solicitante');
  assert.equal(f.selector.classes.has('is-requester'), true);
});

test('CPF and CNPJ masks preserve digits and cap document length', () => {
  const f = fixture();
  f.doc.value = '12345678901'; f.doc.emit('input');
  assert.equal(f.doc.value, '123.456.789-01');
  f.doc.value = '12345678000199'; f.doc.emit('input');
  assert.equal(f.doc.value, '12.345.678/0001-99');
  f.doc.value = '123456780001991234'; f.doc.emit('input');
  assert.equal(f.doc.value, '12.345.678/0001-99');
});

test('registration selection and numeric phone submission still work when the mask CDN fails', () => {
  const f = fixture({ mask: false });
  f.phone.value = '(48) 99999-9999';
  f.toggle.checked = true; f.toggle.emit('change');
  f.form.emit('submit');
  assert.equal(f.phone.value, '48999999999');
  assert.equal(f.profile.value, 'solicitante');
});
