(() => {
    const dialog = document.getElementById('confirmacao-email');
    if (typeof dialog.showModal === 'function') {
        dialog.close();
        dialog.showModal();
        dialog.addEventListener('cancel', event => {
            event.preventDefault();
            window.location.assign(dialog.querySelector('a').href);
        });
    }
    const button = document.getElementById('reenviar-codigo');
    const end = Date.now() + Number(button.dataset.espera) * 1000;
    function update() {
        const seconds = Math.max(0, Math.ceil((end - Date.now()) / 1000));
        button.disabled = seconds > 0;
        button.textContent = seconds
            ? 'Reenviar em ' + Math.floor(seconds / 60) + ':' + String(seconds % 60).padStart(2, '0')
            : 'Reenviar código';
        if (seconds) window.setTimeout(update, 1000);
    }
    update();
})();
