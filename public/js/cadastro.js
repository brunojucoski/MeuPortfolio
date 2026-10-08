document.addEventListener('DOMContentLoaded', function () {
  const form = document.getElementById('form-cadastro');
  if (!form) return;
  const profile = document.getElementById('cadastro-tipo');
  const toggle = document.getElementById('cadastro-profile-switch');
  const selector = document.getElementById('cadastro-profile-toggle');
  const fallback = document.getElementById('cadastro-profile-fallback');
  const title = document.getElementById('cadastro-titulo');
  const illustration = document.getElementById('cadastro-ilustracao');

  function updateProfile() {
    const requester = profile.value === 'solicitante';
    const option = profile.options[requester ? 1 : 0];
    toggle.checked = requester;
    selector.classList.toggle('is-requester', requester);
    title.textContent = option.dataset.title;
    illustration.src = option.dataset.image;
    illustration.alt = option.dataset.imageAlt;
  }

  toggle.addEventListener('change', function () {
    profile.value = toggle.checked ? 'solicitante' : 'artista';
    updateProfile();
  });
  profile.addEventListener('change', updateProfile);
  // Re-synchronize restored browser form values, including back/forward navigation.
  window.addEventListener('pageshow', updateProfile);
  updateProfile();
  selector.hidden = false;
  fallback.hidden = true;

  const documentoInput = document.getElementById('documento');
  const telefoneInput = document.getElementById('telefone');
  const telefoneMask = typeof window.IMask === 'function'
    ? window.IMask(telefoneInput, { mask: '(00) 00000-0000' }) : null;

  function formatDocumento() {
    const value = documentoInput.value.replace(/\D/g, '').slice(0, 14);
    documentoInput.value = value.length <= 11
      ? value.replace(/(\d{0,3})(\d{0,3})(\d{0,3})(\d{0,2})/, (_, p1, p2, p3, p4) =>
        p1 + (p2 ? '.' + p2 : '') + (p3 ? '.' + p3 : '') + (p4 ? '-' + p4 : ''))
      : value.replace(/(\d{0,2})(\d{0,3})(\d{0,3})(\d{0,4})(\d{0,2})/, (_, p1, p2, p3, p4, p5) =>
        p1 + (p2 ? '.' + p2 : '') + (p3 ? '.' + p3 : '') + (p4 ? '/' + p4 : '') + (p5 ? '-' + p5 : ''));
  }
  documentoInput.addEventListener('input', formatDocumento);
  formatDocumento();
  if (!telefoneMask) {
    telefoneInput.addEventListener('input', function () {
      telefoneInput.value = telefoneInput.value.replace(/\D/g, '').slice(0, 11);
    });
  }
  form.addEventListener('submit', function () {
    documentoInput.value = documentoInput.value.replace(/\D/g, '');
    telefoneInput.value = telefoneMask ? telefoneMask.unmaskedValue : telefoneInput.value.replace(/\D/g, '');
  });
});
