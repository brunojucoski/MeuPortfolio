document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('formOrcamentoCategorias');
    if (!form) return;
    const categoria = form.querySelector('[name="id_categoria_orcamento"]');
    const perguntas = form.querySelectorAll('.orcamento-pergunta');
    const enviar = document.getElementById('enviarOrcamentoCategoria');
    const sync = () => {
        let total = 0;
        perguntas.forEach(pergunta => {
            const ativa = categoria.value !== '' && pergunta.dataset.categoria === categoria.value;
            pergunta.hidden = !ativa;
            pergunta.disabled = !ativa;
            if (ativa) total++;
        });
        enviar.disabled = total === 0;
    };
    categoria.addEventListener('change', sync);
    sync();
    if (form.dataset.validationError === 'true') {
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalPropostaContrato')).show();
    }
});
