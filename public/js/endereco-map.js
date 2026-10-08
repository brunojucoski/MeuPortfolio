(function () {
  const BRASIL_CENTER = [-14.235, -51.9253];
  const DEFAULT_ZOOM = 4;
  const LOCATION_ZOOM = 16;
  const states = new WeakMap();

  function onlyDigits(value) {
    return String(value || '').replace(/\D/g, '');
  }

  function formatCep(value) {
    const digits = onlyDigits(value).slice(0, 8);
    return digits.length > 5 ? `${digits.slice(0, 5)}-${digits.slice(5)}` : digits;
  }

  function field(form, name) {
    return form.querySelector(`[name="${name}"]`);
  }

  function stateFor(form) {
    if (!states.has(form)) states.set(form, { version: 0, lastCep: null });
    return states.get(form);
  }

  function cancelPending(form) {
    const state = stateFor(form);
    state.version += 1;
    state.controller?.abort();
    clearTimeout(state.timer);
    return state;
  }

  function startRequest(form) {
    const state = cancelPending(form);
    state.controller = new AbortController();
    return { state, version: state.version, signal: state.controller.signal };
  }

  function isCurrent(operation) {
    return operation.version === operation.state.version;
  }

  function setStatus(form, message) {
    const status = form.querySelector('[data-address-status]');
    if (status) status.textContent = message || '';
  }

  function setFields(form, data) {
    for (const name of ['cep', 'cidade', 'bairro', 'endereco']) {
      const input = field(form, name);
      if (input && data[name] !== undefined) {
        input.value = name === 'cep' ? formatCep(data[name]) : (data[name] || '');
      }
    }
  }

  function validCoords(lat, lng) {
    if (lat == null || lng == null || String(lat).trim() === '' || String(lng).trim() === '') return false;
    return Number.isFinite(Number(lat)) && Number.isFinite(Number(lng))
      && Math.abs(Number(lat)) <= 90 && Math.abs(Number(lng)) <= 180;
  }

  function clearCoords(form, context) {
    for (const name of ['latitude', 'longitude']) {
      const input = field(form, name);
      if (input) input.value = '';
    }
    if (context?.map.hasLayer(context.marker)) context.map.removeLayer(context.marker);
  }

  function setCoords(form, lat, lng, context) {
    if (!validCoords(lat, lng)) return false;
    if (field(form, 'latitude')) field(form, 'latitude').value = Number(lat).toFixed(8);
    if (field(form, 'longitude')) field(form, 'longitude').value = Number(lng).toFixed(8);
    if (context) {
      const coords = [Number(lat), Number(lng)];
      context.marker.setLatLng(coords);
      if (!context.map.hasLayer(context.marker)) context.marker.addTo(context.map);
      context.map.setView(coords, LOCATION_ZOOM, { animate: false });
    }
    return true;
  }

  async function requestJson(url, operation) {
    const controller = operation.state.controller;
    const timer = setTimeout(() => controller.abort(), 20000);
    try {
      const response = await fetch(url, { signal: operation.signal, headers: { Accept: 'application/json' } });
      if (!response.ok) {
        const error = new Error('lookup-failed');
        error.status = response.status;
        throw error;
      }
      return await response.json();
    } finally {
      clearTimeout(timer);
    }
  }

  async function geocodeAddress(form, data, operation) {
    if (!form.dataset.geocodeUrl) return null;
    // O bairro nem sempre faz parte do endereco indexado; pesquisar rua, cidade e UF.
    const q = [data.street || data.cep, data.city, data.state, 'Brasil'].filter(Boolean).join(', ');
    const url = new URL(form.dataset.geocodeUrl, window.location.href);
    url.searchParams.set('q', q);
    const results = await requestJson(url.toString(), operation);
    return Array.isArray(results) ? results[0] : null;
  }

  async function buscarCep(form, context) {
    const cep = onlyDigits(field(form, 'cep')?.value);
    const state = stateFor(form);
    clearTimeout(state.timer);
    if (cep.length !== 8 || state.lastCep === cep) return;
    const operation = startRequest(form);
    state.lastCep = cep;
    clearCoords(form, context);
    setStatus(form, 'Consultando CEP...');
    let data;
    try {
      try {
        data = await requestJson(`https://brasilapi.com.br/api/cep/v2/${cep}`, operation);
      } catch (error) {
        if (!isCurrent(operation) || operation.signal.aborted || [400, 404].includes(error.status)) throw error;
        data = await requestJson(`https://brasilapi.com.br/api/cep/v1/${cep}`, operation);
      }
      if (!isCurrent(operation)) return;
      setFields(form, { cep: data.cep, cidade: data.city, bairro: data.neighborhood, endereco: data.street });
    } catch (error) {
      if (!isCurrent(operation)) return;
      state.lastCep = null;
      setStatus(form, [400, 404].includes(error.status)
        ? 'CEP nao encontrado. Confira os numeros informados.'
        : 'Nao foi possivel consultar o CEP. Tente novamente ou informe o endereco manualmente.');
      return;
    }

    try {
      let coords = data.location?.coordinates;
      if (!validCoords(coords?.latitude, coords?.longitude)) {
        setStatus(form, 'Endereco encontrado. Localizando no mapa...');
        const point = await geocodeAddress(form, data, operation);
        coords = { latitude: point?.lat, longitude: point?.lon };
      }
      if (!isCurrent(operation)) return;
      if (setCoords(form, coords?.latitude, coords?.longitude, context)) {
        setStatus(form, 'Localizacao aproximada do CEP marcada. Ajuste o ponto para indicar o local exato.');
      } else {
        setStatus(form, 'Endereco encontrado, mas sem coordenadas. Selecione o ponto no mapa.');
      }
    } catch (error) {
      if (!isCurrent(operation)) return;
      setStatus(form, 'Endereco encontrado, mas nao foi possivel localizar no mapa. Selecione o ponto manualmente.');
    }
  }

  async function reverseGeocode(form, context, lat, lng) {
    const operation = startRequest(form);
    operation.state.lastCep = onlyDigits(field(form, 'cep')?.value);
    if (!setCoords(form, lat, lng, context)) return;
    setStatus(form, 'Buscando endereco do ponto selecionado...');
    try {
      const url = new URL(form.dataset.reverseUrl, window.location.href);
      url.searchParams.set('lat', lat);
      url.searchParams.set('lon', lng);
      const data = await requestJson(url.toString(), operation);
      if (!isCurrent(operation)) return;
      if (data.error || !data.address) throw new Error('address-unavailable');
      const address = data.address;
      setFields(form, {
        cep: address.postcode || '',
        cidade: address.city || address.town || address.village || address.municipality || '',
        bairro: address.suburb || address.neighbourhood || address.city_district || '',
        endereco: [address.road || address.pedestrian || address.residential || address.hamlet, address.house_number].filter(Boolean).join(', '),
      });
      operation.state.lastCep = onlyDigits(field(form, 'cep')?.value);
      operation.state.inputCep = operation.state.lastCep;
      setStatus(form, 'Endereco carregado pelo mapa.');
    } catch (error) {
      if (!isCurrent(operation)) return;
      setStatus(form, error.status === 429
        ? 'Ponto salvo. Aguarde um instante e selecione novamente para consultar o endereco.'
        : 'Ponto salvo, mas nao foi possivel carregar o endereco. Preencha os campos manualmente.');
    }
  }

  function initMap(form) {
    if (!window.L) return null;
    const mapEl = form.querySelector('[data-address-map]');
    if (!mapEl) return null;
    if (mapEl.dataset.mapReady === '1') return form._addressMapContext;
    mapEl.dataset.mapReady = '1';
    const lat = field(form, 'latitude')?.value;
    const lng = field(form, 'longitude')?.value;
    const hasCoords = validCoords(lat, lng);
    const start = hasCoords ? [Number(lat), Number(lng)] : BRASIL_CENTER;
    const map = window.L.map(mapEl).setView(start, hasCoords ? LOCATION_ZOOM : DEFAULT_ZOOM);
    window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    }).addTo(map);
    const marker = window.L.marker(start, { draggable: true });
    if (hasCoords) marker.addTo(map);
    const context = { map, marker };
    form._addressMapContext = context;
    marker.on('dragend', function () {
      const position = marker.getLatLng();
      reverseGeocode(form, context, position.lat, position.lng);
    });
    map.on('click', event => reverseGeocode(form, context, event.latlng.lat, event.latlng.lng));
    form.closest('.offcanvas')?.addEventListener('shown.bs.offcanvas', function () {
      map.invalidateSize();
      if (validCoords(field(form, 'latitude')?.value, field(form, 'longitude')?.value)) {
        setCoords(form, field(form, 'latitude').value, field(form, 'longitude').value, context);
      }
    });
    return context;
  }

  function initForm(form) {
    if (form.dataset.addressReady === '1') return;
    form.dataset.addressReady = '1';
    const context = initMap(form);
    const state = stateFor(form);
    const cepInput = field(form, 'cep');
    state.inputCep = onlyDigits(cepInput?.value);
    if (validCoords(field(form, 'latitude')?.value, field(form, 'longitude')?.value)) state.lastCep = state.inputCep;
    if (cepInput) {
      cepInput.addEventListener('input', function (event) {
        event.target.value = formatCep(event.target.value);
        const cep = onlyDigits(event.target.value);
        if (cep === state.inputCep) return;
        state.inputCep = cep;
        cancelPending(form);
        state.lastCep = null;
        clearCoords(form, context);
        setStatus(form, '');
        if (cep.length === 8) state.timer = setTimeout(() => buscarCep(form, context), 600);
      });
      cepInput.addEventListener('blur', () => buscarCep(form, context));
    }
    for (const name of ['cidade', 'bairro', 'endereco']) {
      field(form, name)?.addEventListener('input', () => {
        cancelPending(form);
        setStatus(form, '');
      });
    }
  }

  window.buscarCEP = function (cep) {
    const form = document.querySelector('[data-address-form]') || document.querySelector('#editOffcanvas form');
    if (!form) return;
    if (field(form, 'cep')) field(form, 'cep').value = formatCep(cep);
    return buscarCep(form, initMap(form));
  };

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-address-form]').forEach(initForm);
  });
})();
