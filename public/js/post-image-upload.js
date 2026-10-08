document.addEventListener('DOMContentLoaded', () => {
    if (typeof DataTransfer === 'undefined') return;
    document.querySelectorAll('[data-post-image-upload]').forEach(root => {
        const input = root.querySelector('input[type="file"]');
        const dropzone = root.querySelector('.post-image-dropzone');
        const previews = root.querySelector('[data-upload-previews]');
        const error = root.querySelector('[data-upload-error]');
        const status = root.querySelector('[data-upload-status]');
        const maxFiles = Number(root.dataset.maxFiles);
        const maxSize = Number(root.dataset.maxSize);
        const maxTotal = Number(root.dataset.maxTotal);
        let selected = [];
        let nextId = 0;
        let dragDepth = 0;
        root.classList.add('is-enhanced');

        const fileKey = file => [file.name, file.size, file.lastModified, file.type].join(':');
        const sizeLabel = size => size >= 1048576 ? `${(size / 1048576).toFixed(1)} MB` : `${Math.ceil(size / 1024)} KB`;
        function syncFiles() {
            // Keep the native multipart field identical to the files still shown in the preview.
            const transfer = new DataTransfer();
            selected.forEach(item => transfer.items.add(item.file));
            input.files = transfer.files;
            status.textContent = selected.length ? `${selected.length} ${selected.length === 1 ? 'imagem selecionada' : 'imagens selecionadas'} · ${sizeLabel(selected.reduce((total, item) => total + item.file.size, 0))}` : '';
        }

        function render() {
            previews.replaceChildren();
            selected.forEach(item => {
                const tile = document.createElement('li');
                tile.className = 'post-image-preview';
                const image = document.createElement('img');
                image.src = item.url;
                image.alt = item.file.name;
                const name = document.createElement('span');
                name.className = 'post-image-preview-name';
                name.textContent = item.file.name;
                name.title = item.file.name;
                const size = document.createElement('span');
                size.className = 'post-image-preview-size';
                size.textContent = sizeLabel(item.file.size);
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'post-image-remove';
                remove.dataset.removeImage = item.id;
                remove.setAttribute('aria-label', `Remover ${item.file.name}`);
                remove.title = `Remover ${item.file.name}`;
                const icon = document.createElement('i');
                icon.className = 'bi bi-trash';
                icon.setAttribute('aria-hidden', 'true');
                remove.append(icon);
                tile.append(image, name, size, remove);
                previews.append(tile);
            });
        }

        function addFiles(files) {
            const messages = [];
            for (const file of files) {
                if (selected.some(item => fileKey(item.file) === fileKey(file))) continue;
                const validType = ['image/jpeg', 'image/png', 'image/gif'].includes(file.type)
                    || (!file.type && /\.(jpe?g|png|gif)$/i.test(file.name));
                if (!validType || file.size === 0) {
                    messages.push(`${file.name}: selecione uma imagem JPG, PNG ou GIF.`);
                } else if (file.size > maxSize) {
                    messages.push(`${file.name}: o limite por imagem é 8 MB.`);
                } else if (selected.length >= maxFiles) {
                    messages.push(`O limite é de ${maxFiles} imagens.`);
                    break;
                } else if (maxTotal && selected.reduce((total, item) => total + item.file.size, 0) + file.size > maxTotal) {
                    messages.push(`${file.name}: o conjunto de imagens ultrapassa o limite de envio (${sizeLabel(maxTotal)}).`);
                } else {
                    selected.push({ id: String(++nextId), file, url: URL.createObjectURL(file) });
                }
            }
            error.textContent = messages.join(' ');
            error.hidden = messages.length === 0;
            syncFiles();
            render();
        }

        function clear() {
            selected.forEach(item => URL.revokeObjectURL(item.url));
            selected = [];
            error.textContent = '';
            error.hidden = true;
            dragDepth = 0;
            dropzone.classList.remove('is-dragging');
            syncFiles();
            render();
        }

        input.addEventListener('change', () => addFiles([...input.files]));
        previews.addEventListener('click', event => {
            const button = event.target.closest('[data-remove-image]');
            if (!button) return;
            const index = selected.findIndex(item => item.id === button.dataset.removeImage);
            if (index < 0) return;
            URL.revokeObjectURL(selected[index].url);
            selected.splice(index, 1);
            syncFiles();
            render();
            const buttons = previews.querySelectorAll('[data-remove-image]');
            (buttons[Math.min(index, buttons.length - 1)] || input).focus();
        });
        dropzone.addEventListener('dragenter', event => {
            event.preventDefault();
            dragDepth++;
            dropzone.classList.add('is-dragging');
        });
        dropzone.addEventListener('dragover', event => {
            event.preventDefault();
            event.dataTransfer.dropEffect = 'copy';
        });
        dropzone.addEventListener('dragleave', () => {
            dragDepth = Math.max(0, dragDepth - 1);
            if (!dragDepth) dropzone.classList.remove('is-dragging');
        });
        dropzone.addEventListener('drop', event => {
            event.preventDefault();
            dragDepth = 0;
            dropzone.classList.remove('is-dragging');
            addFiles([...event.dataTransfer.files]);
        });
        root.addEventListener('post-images-reset', clear);
        input.form?.addEventListener('reset', () => queueMicrotask(clear));
        window.addEventListener('pagehide', event => { if (!event.persisted) clear(); });
    });
});
