(function () {
  const BRASIL_CENTER = [-14.235, -51.9253];
  let map = null;
  let markers = null;
  let searchArea = null;
  let centerMarker = null;
  let initialCenter = BRASIL_CENTER;
  let config = null;
  let searchTimer = null;
  let pendingRequest = null;
  let requestVersion = 0;
  let loadedCenter = null;

  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function displayName(artist) {
    return artist.nome_artistico || artist.nome || 'Artista';
  }

  function locationText(artist) {
    return [artist.bairro, artist.cidade].filter(Boolean).join(' - ') || 'Localidade informada no mapa';
  }

  function categoriesText(artist) {
    return Array.isArray(artist.categorias) && artist.categorias.length
      ? artist.categorias.join(', ')
      : 'Categorias não informadas';
  }

  function ratingText(artist) {
    if (!artist.avaliacao_media) return 'Sem avaliações ainda';

    const total = Number(artist.avaliacao_total || 0);
    return `${artist.avaliacao_media} (${total} ${total === 1 ? 'avaliação' : 'avaliações'})`;
  }

  function markerHtml(artist) {
    const name = escapeHtml(displayName(artist));
    const city = escapeHtml(artist.cidade || 'Localidade informada');
    const photo = escapeHtml(artist.foto);

    return `
      <div class="artistas-map-pin" aria-hidden="true"></div>
      <div class="artistas-map-preview">
        <img src="${photo}" alt="">
        <span>
          <strong>${name}</strong>
          <span>${city}</span>
        </span>
      </div>
    `;
  }

  function popupHtml(artist) {
    const name = escapeHtml(displayName(artist));
    const photo = escapeHtml(artist.foto);
    const profile = escapeHtml(artist.perfil_url);
    const categories = escapeHtml(categoriesText(artist));
    const location = escapeHtml(locationText(artist));
    const rating = escapeHtml(ratingText(artist));

    return `
      <div class="artistas-map-popup-card">
        <img src="${photo}" alt="Foto de ${name}">
        <div>
          <h3>${name}</h3>
          <p>${location}<br>${categories}<br>${rating}</p>
          <a href="${profile}">Ver perfil</a>
        </div>
      </div>
    `;
  }

  function renderArtists(artists) {
    markers.clearLayers();
    artists.forEach(function (artist) {
      if (!Number.isFinite(Number(artist.latitude)) || !Number.isFinite(Number(artist.longitude))) return;
      const coords = [Number(artist.latitude), Number(artist.longitude)];
      const icon = L.divIcon({
        className: 'artistas-map-marker',
        html: markerHtml(artist),
        iconSize: [28, 28],
        iconAnchor: [14, 28],
        popupAnchor: [0, -30],
      });

      L.marker(coords, { icon, title: displayName(artist), alt: displayName(artist), riseOnHover: true })
        .addTo(markers)
        .bindPopup(popupHtml(artist), {
          className: 'artistas-map-popup',
          maxWidth: 280,
          autoPan: false,
        });
    });
  }

  function setStatus(count, message) {
    document.querySelector('[data-map-count]').textContent = count;
    const notice = document.querySelector('[data-map-empty-message]');
    notice.textContent = message || '';
    notice.classList.toggle('d-none', !message);
  }

  function cancelSearch() {
    clearTimeout(searchTimer);
    if (pendingRequest) pendingRequest.abort();
    pendingRequest = null;
    requestVersion += 1;
    document.getElementById('artistas-map').setAttribute('aria-busy', 'false');
  }

  function currentCenter() {
    const center = map.getCenter().wrap();
    return L.latLng(Math.max(-90, Math.min(90, center.lat)), center.lng);
  }

  async function loadArtists() {
    if (!document.getElementById('artistas-view-switch').checked) return;
    const center = currentCenter();
    if (loadedCenter && center.distanceTo(loadedCenter) < 1) return;

    cancelSearch();
    const version = requestVersion;
    const controller = new AbortController();
    pendingRequest = controller;
    markers.clearLayers();
    setStatus('Carregando...', '');
    document.getElementById('artistas-map').setAttribute('aria-busy', 'true');

    const url = new URL(config.endpoint, window.location.origin);
    url.searchParams.set('latitude', center.lat.toFixed(8));
    url.searchParams.set('longitude', center.lng.toFixed(8));
    ['categoria', 'cidade'].forEach(function (name) {
      const field = document.querySelector(`#filtroForm [name="${name}"]`);
      if (field.value) url.searchParams.set(name, field.value);
    });

    try {
      const response = await fetch(url, { signal: controller.signal, headers: { Accept: 'application/json' } });
      if (!response.ok) throw new Error('Map search failed');
      const data = await response.json();
      if (version !== requestVersion) return;
      renderArtists(data.artistas);
      loadedCenter = center;
      const count = data.artistas.length;
      setStatus(`${count} artista${count === 1 ? '' : 's'}`, data.tem_mais
        ? `Exibindo os ${data.limite} artistas mais próximos desta área.`
        : (!count ? 'Nenhum artista encontrado nesta área para os filtros atuais.' : ''));
    } catch (error) {
      if (error.name !== 'AbortError' && version === requestVersion) {
        setStatus('Indisponível', 'Não foi possível carregar os artistas.');
        loadedCenter = null;
      }
    } finally {
      if (version === requestVersion) {
        pendingRequest = null;
        document.getElementById('artistas-map').setAttribute('aria-busy', 'false');
      }
    }
  }

  function initMap() {
    if (map) return;
    if (!window.L) {
      setStatus('Indisponível', 'Não foi possível carregar o mapa.');
      return;
    }
    initialCenter = config.centro || BRASIL_CENTER;
    map = L.map('artistas-map', { scrollWheelZoom: true, worldCopyJump: true })
      .setView(initialCenter, 10);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
      maxZoom: 19,
    }).addTo(map);
    markers = L.layerGroup().addTo(map);
    const color = getComputedStyle(document.documentElement).getPropertyValue('--roxo-appolo').trim() || '#6d2e2e';
    searchArea = L.circle(initialCenter, {
      radius: config.raioKm * 1000, color, weight: 2, fillOpacity: 0.06, interactive: false,
    }).addTo(map);
    centerMarker = L.marker(initialCenter, {
      icon: L.divIcon({ className: 'artistas-map-center', iconSize: [10, 10] }),
      interactive: false, keyboard: false,
    }).addTo(map);
    map.fitBounds(searchArea.getBounds(), { padding: [20, 20], maxZoom: 11 });
    map.on('movestart', cancelSearch);
    map.on('move', function () {
      const center = currentCenter();
      searchArea.setLatLng(center);
      centerMarker.setLatLng(center);
      if (!loadedCenter || center.distanceTo(loadedCenter) >= 1) {
        markers.clearLayers();
        loadedCenter = null;
      }
    });
    map.on('moveend', function () {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(loadArtists, 300);
    });
  }

  function setViewMode(isMap) {
    document.getElementById('visualizacao-artistas').value = isMap ? 'mapa' : 'lista';
    document.querySelector('.artistas-view-toggle').classList.toggle('is-map', isMap);
    const url = new URL(window.location.href);
    url.searchParams.set('visualizacao_artistas', isMap ? 'mapa' : 'lista');
    window.history.replaceState(window.history.state, '', url);
    if (isMap) showMap();
    else showCards();
  }

  function showMap() {
    const mapView = document.getElementById('artistas-map-view');
    const listView = document.getElementById('artistas-list-view');
    if (!mapView || !listView) return;

    mapView.classList.remove('d-none');
    listView.classList.add('d-none');
    initMap();

    if (map) {
      map.invalidateSize({ pan: false });
      loadArtists();
    }
  }

  function showCards() {
    const mapView = document.getElementById('artistas-map-view');
    const listView = document.getElementById('artistas-list-view');
    if (!mapView || !listView) return;

    mapView.classList.add('d-none');
    listView.classList.remove('d-none');
    cancelSearch();
  }

  document.addEventListener('DOMContentLoaded', function () {
    const toggle = document.getElementById('artistas-view-switch');
    if (!toggle) return;
    config = JSON.parse(document.getElementById('artistas-map-config').textContent);
    toggle.addEventListener('change', function () { setViewMode(toggle.checked); });
    document.querySelector('[data-map-reset]').addEventListener('click', function () {
      if (!map) return;
      loadedCenter = null;
      searchArea.setLatLng(initialCenter);
      map.fitBounds(searchArea.getBounds(), { padding: [20, 20], maxZoom: 11, animate: false });
      loadArtists();
    });
    if (toggle.checked) showMap();
  });
})();
