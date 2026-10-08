const assert = require('node:assert/strict');
const { test } = require('node:test');
const { readFileSync } = require('node:fs');
const { resolve } = require('node:path');
const { runInNewContext } = require('node:vm');

class Element {
  constructor(value = '') { this.value = value; this.dataset = {}; this.events = {}; this.textContent = ''; }
  addEventListener(event, handler) { this.events[event] = handler; }
  emit(event, data = {}) { return this.events[event]?.({ target: this, ...data }); }
}

function fixture({ initial = {}, leaflet = true } = {}) {
  const fields = Object.fromEntries(['cep', 'cidade', 'bairro', 'endereco', 'latitude', 'longitude']
    .map(name => [name, new Element(initial[name] || '')]));
  const form = new Element();
  const mapEl = new Element();
  const status = new Element();
  const panel = new Element();
  form.dataset.geocodeUrl = 'http://localhost/endereco/localizar';
  form.dataset.reverseUrl = 'http://localhost/endereco/reverso';
  form.querySelector = selector => {
    if (selector === '[data-address-map]') return mapEl;
    if (selector === '[data-address-status]') return status;
    return fields[selector.match(/name="([^"]+)"/)?.[1]];
  };
  form.closest = () => panel;
  const map = new Element();
  map.layers = new Set();
  map.setView = (coords, zoom) => { map.coords = Array.from(coords); map.zoom = zoom; return map; };
  map.hasLayer = layer => map.layers.has(layer);
  map.removeLayer = layer => map.layers.delete(layer);
  map.invalidateSize = () => { map.invalidated = true; };
  const marker = new Element();
  marker.setLatLng = coords => { marker.coords = Array.from(coords); return marker; };
  marker.addTo = m => { m.layers.add(marker); return marker; };
  marker.getLatLng = () => ({ lat: marker.coords[0], lng: marker.coords[1] });
  const L = {
    map: () => map,
    tileLayer: () => ({ addTo() {} }),
    marker: coords => marker.setLatLng(coords),
  };
  map.on = marker.on = Element.prototype.addEventListener;
  const timers = new Map();
  let timerId = 0;
  const requests = [];
  const fetch = (url, options) => new Promise(resolve => requests.push({ url, options, resolve }));
  const document = new Element();
  document.querySelectorAll = () => [form];
  document.querySelector = () => form;
  const window = { L: leaflet ? L : null, location: { href: 'http://localhost/cadastro/artista' } };
  runInNewContext(readFileSync(resolve(__dirname, '../../public/js/endereco-map.js'), 'utf8'), {
    window, document, fetch, URL, AbortController,
    setTimeout: (handler, delay) => { timers.set(++timerId, { handler, delay }); return timerId; },
    clearTimeout: id => timers.delete(id),
  });
  document.emit('DOMContentLoaded');
  return {
    fields, form, map, marker, status, panel, requests, timers,
    input(cep) { fields.cep.value = cep; fields.cep.emit('input'); },
    blur() { return fields.cep.emit('blur'); },
    debounce() {
      const entry = [...timers.entries()].find(([, timer]) => timer.delay === 600);
      if (entry) { timers.delete(entry[0]); return entry[1].handler(); }
    },
    click(lat, lng) { return map.emit('click', { latlng: { lat, lng } }); },
    async reply(index, body, status = 200) {
      requests[index].resolve({ ok: status >= 200 && status < 300, status, json: async () => body });
      for (let i = 0; i < 8; i++) await new Promise(resolve => setImmediate(resolve));
    },
  };
}

const address = {
  cep: '88801001', city: 'Criciuma', state: 'SC', street: 'Rua de teste', neighborhood: 'Centro',
  location: { coordinates: { latitude: '-28.6775', longitude: '-49.3697' } },
};

test('new forms have no fake marker; existing coordinates initialize the map', () => {
  const blank = fixture();
  assert.equal(blank.map.layers.size, 0);
  assert.equal(blank.map.zoom, 4);
  const saved = fixture({ initial: { latitude: '-28.6775', longitude: '-49.3697', cep: '88801-001' } });
  assert.equal(saved.map.layers.size, 1);
  assert.equal(saved.map.zoom, 16);
  saved.panel.emit('shown.bs.offcanvas');
  assert.equal(saved.map.invalidated, true);
  assert.deepEqual(saved.map.coords, [-28.6775, -49.3697]);
});

test('a complete CEP automatically fills address, marker and hidden coordinates, without duplicate blur requests', async () => {
  const f = fixture();
  f.input('88801001');
  assert.equal(f.fields.cep.value, '88801-001');
  f.debounce();
  f.blur();
  assert.equal(f.requests.length, 1);
  assert.match(f.requests[0].url, /\/cep\/v2\/88801001$/);
  await f.reply(0, address);
  assert.equal(f.fields.cidade.value, 'Criciuma');
  assert.equal(f.fields.endereco.value, 'Rua de teste');
  assert.equal(f.fields.bairro.value, 'Centro');
  assert.equal(f.fields.latitude.value, '-28.67750000');
  assert.equal(f.fields.longitude.value, '-49.36970000');
  assert.deepEqual(f.marker.coords, [-28.6775, -49.3697]);
  assert.equal(f.map.layers.size, 1);
  assert.equal(f.map.zoom, 16);
  assert.match(f.status.textContent, /aproximada/);
  f.blur();
  assert.equal(f.requests.length, 1);
});

test('missing API coordinates fall back to address lookup without over-specific neighborhood', async () => {
  const f = fixture();
  f.input('88801001'); f.blur();
  await f.reply(0, { ...address, location: { coordinates: { latitude: null, longitude: null } } });
  const url = new URL(f.requests[1].url);
  assert.equal(url.pathname, '/endereco/localizar');
  assert.equal(url.searchParams.get('q'), 'Rua de teste, Criciuma, SC, Brasil');
  await f.reply(1, [{ lat: '-28.68', lon: '-49.37' }]);
  assert.equal(f.fields.latitude.value, '-28.68000000');
  assert.equal(f.map.layers.size, 1);
});

test('v2 outages fall back to v1 and geocoding without losing the address', async () => {
  const f = fixture();
  f.input('88801001'); f.blur();
  await f.reply(0, {}, 500);
  assert.match(f.requests[1].url, /\/cep\/v1\//);
  await f.reply(1, { ...address, location: undefined });
  await f.reply(2, [], 503);
  assert.equal(f.fields.endereco.value, 'Rua de teste');
  assert.equal(f.fields.latitude.value, '');
  assert.match(f.status.textContent, /Endereco encontrado/);
  assert.doesNotMatch(f.status.textContent, /CEP nao encontrado/);
});

test('empty or invalid coordinates never mark zero or preserve the previous address point', async () => {
  const f = fixture({ initial: { latitude: '-28', longitude: '-49', cep: '11111-111' } });
  f.input('88801001'); f.blur();
  assert.equal(f.map.layers.size, 0);
  await f.reply(0, { ...address, location: { coordinates: { latitude: '', longitude: '200' } } });
  await f.reply(1, []);
  assert.equal(f.fields.latitude.value, '');
  assert.equal(f.map.layers.size, 0);
  assert.match(f.status.textContent, /sem coordenadas/);
});

test('invalid CEP does not trigger geocoding and incomplete CEP never sends a request', async () => {
  const f = fixture();
  f.input('88801'); f.blur(); f.debounce();
  assert.equal(f.requests.length, 0);
  f.input('00000000'); f.blur();
  await f.reply(0, {}, 404);
  assert.equal(f.requests.length, 1);
  assert.match(f.status.textContent, /CEP nao encontrado/);
});

test('late responses cannot overwrite a newer CEP', async () => {
  const f = fixture();
  f.input('88801001'); f.blur();
  f.input('01001000'); f.blur();
  assert.equal(f.requests[0].options.signal.aborted, true);
  await f.reply(1, { ...address, cep: '01001000', city: 'Sao Paulo', location: { coordinates: { latitude: '-23.55', longitude: '-46.63' } } });
  await f.reply(0, address);
  assert.equal(f.fields.cidade.value, 'Sao Paulo');
  assert.equal(f.fields.cep.value, '01001-000');
  assert.deepEqual(f.map.coords, [-23.55, -46.63]);
});

test('manual map selection wins over pending CEP, loads reverse address and prevents unwanted relocation on blur', async () => {
  const f = fixture();
  f.input('88801001'); f.blur();
  f.click(-28.7, -49.4);
  assert.equal(new URL(f.requests[1].url).pathname, '/endereco/reverso');
  await f.reply(1, { address: { postcode: '88802000', road: 'Outra rua', house_number: '10', town: 'Criciuma', suburb: 'Bairro novo' } });
  await f.reply(0, address);
  assert.equal(f.fields.endereco.value, 'Outra rua, 10');
  assert.equal(f.fields.bairro.value, 'Bairro novo');
  assert.equal(f.fields.latitude.value, '-28.70000000');
  f.blur();
  assert.equal(f.requests.length, 2);
});

test('reverse lookup failure keeps the explicitly selected coordinates', async () => {
  const f = fixture();
  f.click(-28.7, -49.4);
  await f.reply(0, {}, 429);
  assert.equal(f.fields.latitude.value, '-28.70000000');
  assert.match(f.status.textContent, /Ponto salvo/);
});

test('address and coordinates still load if Leaflet is unavailable', async () => {
  const f = fixture({ leaflet: false });
  f.input('88801001'); f.blur();
  await f.reply(0, address);
  assert.equal(f.fields.cidade.value, 'Criciuma');
  assert.equal(f.fields.longitude.value, '-49.36970000');
});
