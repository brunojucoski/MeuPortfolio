<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formulário de propostas</title>
    <link href="{{ asset('css/perfil.css') }}" rel="stylesheet">
    <link href="{{ asset('css/perguntas-proposta.css') }}" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
</head>
<body>
@include('Components.navbarbootstrap')

<main class="container perguntas-proposta-page my-5">
    <div class="perguntas-proposta-header">
        <div>
            <h2 class="botao_home text-uppercase mb-2">Formulário de Orçamento</h2>
            <p class="text-muted mb-0">Categorias de orçamento</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    @php
        $categoriaAberta = session('categoria_orcamento_aberta');
        if ($categoriaAberta === null && in_array(old('_form'), ['nova_pergunta', 'editar_pergunta'])) {
            $categoriaAberta = old('id_categoria_orcamento');
        }
        $tipoLabels = ['texto' => 'Texto livre', 'opcoes' => 'Opções', 'anexo' => 'Anexo'];
    @endphp
    <section class="orcamento-categorias mb-4" aria-label="Categorias de orçamento">
        <div class="perguntas-list-toolbar">
            <h5 class="mb-0">Categorias</h5>
            <button type="button" class="btn btn-outline-custom perguntas-icon-btn" onclick="openCategoriaOrcamentoModal()" title="Nova categoria" aria-label="Nova categoria"><i class="bi bi-plus-lg"></i></button>
        </div>
        <div class="orcamento-categorias-list">
            @forelse($categoriasOrcamento as $categoria)
                @php
                    $aberta = (string) $categoriaAberta === (string) $categoria->id;
                    $totalPerguntas = $categoria->perguntas->count();
                @endphp
                <article class="orcamento-categoria-card" data-orcamento-categoria="{{ $categoria->id }}">
                    <div class="orcamento-categoria-header">
                        <button type="button" class="orcamento-categoria-toggle {{ $aberta ? '' : 'collapsed' }}"
                            id="categoriaOrcamentoToggle{{ $categoria->id }}" data-bs-toggle="collapse"
                            data-bs-target="#perguntasCategoria{{ $categoria->id }}" aria-expanded="{{ $aberta ? 'true' : 'false' }}"
                            aria-controls="perguntasCategoria{{ $categoria->id }}">
                            <span class="orcamento-categoria-order"><small>Ordem</small><span>{{ $categoria->ordem }}</span></span>
                            <span class="orcamento-categoria-name"><strong>{{ $categoria->nome }}</strong><small>{{ $totalPerguntas }} pergunta{{ $totalPerguntas === 1 ? '' : 's' }}</small></span>
                            <i class="bi bi-chevron-down" aria-hidden="true"></i>
                        </button>
                        <div class="perguntas-list-actions orcamento-categoria-actions">
                            <button type="button" class="btn btn-outline-custom perguntas-icon-btn" data-categoria-id="{{ $categoria->id }}" data-url="{{ route('categorias-orcamento.update', $categoria) }}" data-nome="{{ $categoria->nome }}" data-ordem="{{ $categoria->ordem }}" onclick="openCategoriaOrcamentoModal(this)" title="Editar categoria" aria-label="Editar categoria {{ $categoria->nome }}"><i class="bi bi-journal-text"></i></button>
                            <form action="{{ route('categorias-orcamento.destroy', $categoria) }}" method="POST" onsubmit="return confirm('Excluir esta categoria?');">@csrf @method('DELETE')<button class="btn btn-outline-danger perguntas-icon-btn" title="Excluir categoria" aria-label="Excluir categoria {{ $categoria->nome }}"><i class="bi bi-trash"></i></button></form>
                        </div>
                    </div>
                    <div id="perguntasCategoria{{ $categoria->id }}" class="collapse {{ $aberta ? 'show' : '' }}" role="region" aria-labelledby="categoriaOrcamentoToggle{{ $categoria->id }}">
                        <div class="orcamento-categoria-body">
                            <div class="perguntas-list-toolbar">
                                <h5 class="mb-0">Perguntas</h5>
                                <button type="button" class="btn btn-outline-custom perguntas-icon-btn"
                                    data-nova-pergunta-categoria="{{ $categoria->id }}" data-categoria-nome="{{ $categoria->nome }}"
                                    data-proxima-ordem="{{ $totalPerguntas ? $categoria->perguntas->max('ordem') + 1 : 0 }}"
                                    onclick="openNovaPerguntaPropostaModal(this)" title="Adicionar pergunta"
                                    aria-label="Adicionar pergunta em {{ $categoria->nome }}"><i class="bi bi-plus-lg" aria-hidden="true"></i></button>
                            </div>
                            <div class="perguntas-list">
                                @if($totalPerguntas)
                                <div class="perguntas-list-head" aria-hidden="true">
                                    <span>Ordem</span>
                                    <span>Tipo</span>
                                    <span>Título</span>
                                    <span>Ações</span>
                                </div>
                                @endif

                                @forelse($categoria->perguntas as $p)
                                    @php
                                        $opcoes = $p->opcoesList();
                                    @endphp
                                    <div class="perguntas-list-row" data-pergunta-categoria="{{ $p->id_categoria_orcamento }}">
                                        <span class="perguntas-list-order">{{ $p->ordem }}</span>
                                        <span class="perguntas-list-type">{{ $tipoLabels[$p->tipo] ?? $p->tipo }}</span>
                                        <span class="perguntas-list-title" title="{{ $p->titulo }}">
                                            {{ $p->titulo }}
                                            @if($p->tipo === 'opcoes' && count($opcoes))
                                                <small>{{ implode(' | ', $opcoes) }}</small>
                                            @endif
                                        </span>
                                        <span class="perguntas-list-actions">
                                            <button
                                                type="button"
                                                class="btn btn-outline-custom btn-sm perguntas-icon-btn"
                                                data-update-url="{{ route('perguntas-proposta.update', $p) }}"
                                                data-pergunta-titulo="{{ $p->titulo }}"
                                                data-pergunta-id="{{ $p->id }}"
                                                data-pergunta-ordem="{{ $p->ordem }}"
                                                data-pergunta-tipo="{{ $p->tipo }}"
                                                data-pergunta-categoria="{{ $p->id_categoria_orcamento }}"
                                                data-categoria-nome="{{ $categoria->nome }}"
                                                data-pergunta-opcoes="{{ base64_encode(json_encode($opcoes)) }}"
                                                onclick="openEditPerguntaPropostaModal(this)"
                                                aria-label="Editar pergunta {{ $p->titulo }}"
                                                title="Editar pergunta"
                                            >
                                                <i class="bi bi-journal-text"></i>
                                            </button>
                                            <form action="{{ route('perguntas-proposta.destroy', $p) }}" method="POST" class="d-inline" onsubmit="return confirm('Excluir esta pergunta?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger btn-sm perguntas-icon-btn" aria-label="Excluir pergunta {{ $p->titulo }}" title="Excluir pergunta">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </span>
                                    </div>
                                @empty
                                    <div class="perguntas-empty">
                                        Nenhuma pergunta nesta categoria.
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </article>
            @empty
                <div class="perguntas-empty">Nenhuma categoria cadastrada.</div>
            @endforelse
        </div>
    </section>

    <a href="{{ route('usuarios.perfilPublico', Auth::id()) }}" class="btn btn-outline-secondary mt-3">Voltar ao perfil</a>
</main>

<div class="modal fade" id="categoriaOrcamentoModal" tabindex="-1" aria-labelledby="categoriaOrcamentoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form id="categoriaOrcamentoForm" action="{{ route('categorias-orcamento.store') }}" data-store-url="{{ route('categorias-orcamento.store') }}" method="POST">
            @csrf
            <input type="hidden" name="_form" value="categoria_orcamento">
            <input type="hidden" name="_categoria_id" id="categoriaOrcamentoId">
            <input type="hidden" name="_method" id="categoriaOrcamentoMethod" value="PUT" disabled>
            <div class="modal-header"><h5 id="categoriaOrcamentoModalLabel" class="modal-title">Nova categoria</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
            <div class="modal-body">
                <label for="categoriaOrcamentoNome" class="form-label">Nome da categoria</label><input id="categoriaOrcamentoNome" name="nome" class="form-control mb-3" maxlength="120" required>
                <label for="categoriaOrcamentoOrdem" class="form-label">Ordem</label><input id="categoriaOrcamentoOrdem" name="ordem" type="number" class="form-control" min="0" value="0">
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-outline-custom">Salvar</button></div>
        </form>
    </div></div>
</div>

<div class="modal fade" id="novaPerguntaPropostaModal" tabindex="-1" aria-labelledby="novaPerguntaPropostaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('perguntas-proposta.store') }}" method="POST" id="formNovaPergunta">
                @csrf
                <input type="hidden" name="_form" value="nova_pergunta">
                <input type="hidden" name="id_categoria_orcamento" id="categoriaNovaPergunta" required>
                <div class="modal-header">
                    <h5 class="modal-title" id="novaPerguntaPropostaModalLabel">Nova pergunta</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <span class="text-muted small">Categoria</span>
                        <strong class="d-block pergunta-categoria-context" id="categoriaNovaPerguntaNome"></strong>
                    </div>
                    <div class="mb-3">
                        <label for="tipoNovaPergunta" class="form-label">Tipo</label>
                        <select name="tipo" class="form-select" id="tipoNovaPergunta" required>
                            <option value="texto">Texto livre</option>
                            <option value="opcoes">Opções (escolha única)</option>
                            <option value="anexo">Anexo (arquivo)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="tituloNovaPergunta" class="form-label">Título da pergunta</label>
                        <input type="text" name="titulo" id="tituloNovaPergunta" class="form-control" required maxlength="500" placeholder="Ex.: Qual o tipo de evento?">
                    </div>
                    <div class="mb-3">
                        <label for="ordemNovaPergunta" class="form-label">Ordem</label>
                        <input type="number" name="ordem" id="ordemNovaPergunta" class="form-control" value="0" min="0">
                    </div>
                    <div class="mb-0 d-none" id="blocoOpcoesNova">
                        <label class="form-label">Opções de resposta</label>
                        <div id="opcoesContainerNova">
                            <input type="text" name="opcoes[]" class="form-control mb-2" placeholder="Opção 1">
                            <input type="text" name="opcoes[]" class="form-control mb-2" placeholder="Opção 2">
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnAddOpcaoNova">
                            <i class="bi bi-plus-lg"></i> Opção
                        </button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-outline-custom">Adicionar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editarPerguntaPropostaModal" tabindex="-1" aria-labelledby="editarPerguntaPropostaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="editarPerguntaPropostaForm" action="" method="POST">
                @csrf
                <input type="hidden" name="_form" value="editar_pergunta">
                <input type="hidden" name="_pergunta_id" id="editarPerguntaId">
                <input type="hidden" name="id_categoria_orcamento" id="editar_pergunta_categoria">
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title" id="editarPerguntaPropostaModalLabel">Editar pergunta</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="editar_pergunta_tipo" class="form-label">Tipo</label>
                        <input type="text" id="editar_pergunta_tipo" class="form-control" readonly>
                        <div class="form-text">O tipo da pergunta é definido na criação.</div>
                    </div>
                    <div class="mb-3">
                        <span class="text-muted small">Categoria</span>
                        <strong class="d-block pergunta-categoria-context" id="editarPerguntaCategoriaNome"></strong>
                    </div>
                    <div class="mb-3">
                        <label for="editar_pergunta_titulo" class="form-label">Título da pergunta</label>
                        <input type="text" name="titulo" id="editar_pergunta_titulo" class="form-control" required maxlength="500">
                    </div>
                    <div class="mb-3">
                        <label for="editar_pergunta_ordem" class="form-label">Ordem</label>
                        <input type="number" name="ordem" id="editar_pergunta_ordem" class="form-control" min="0">
                    </div>
                    <div class="mb-0 d-none" id="blocoOpcoesEditar">
                        <label class="form-label">Opções de resposta</label>
                        <div id="opcoesContainerEditar"></div>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnAddOpcaoEditar">
                            <i class="bi bi-plus-lg"></i> Opção
                        </button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-outline-custom">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

@include('Components.footer')
<script>
    function openCategoriaOrcamentoModal(button = null) {
        const form = document.getElementById('categoriaOrcamentoForm');
        form.action = button?.dataset.url || form.dataset.storeUrl;
        document.getElementById('categoriaOrcamentoMethod').disabled = !button;
        document.getElementById('categoriaOrcamentoId').value = button?.dataset.categoriaId || '';
        document.getElementById('categoriaOrcamentoNome').value = button?.dataset.nome || '';
        document.getElementById('categoriaOrcamentoOrdem').value = button?.dataset.ordem || 0;
        document.getElementById('categoriaOrcamentoModalLabel').textContent = button ? 'Editar categoria' : 'Nova categoria';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('categoriaOrcamentoModal')).show();
    }
    const tipoLabels = {
        texto: 'Texto livre',
        opcoes: 'Opções (escolha única)',
        anexo: 'Anexo (arquivo)'
    };

    function createOptionInput(value = '', placeholder = 'Outra opção', className = 'form-control mb-2') {
        const input = document.createElement('input');
        input.type = 'text';
        input.name = 'opcoes[]';
        input.className = className;
        input.placeholder = placeholder;
        input.value = value;
        return input;
    }

    function setOptionsEnabled(container, enabled) {
        container.querySelectorAll('input[name="opcoes[]"]').forEach((input) => {
            input.disabled = !enabled;
        });
    }

    function syncOpcoesNova() {
        const tipoNova = document.getElementById('tipoNovaPergunta');
        const blocoOpcoes = document.getElementById('blocoOpcoesNova');
        if (!tipoNova || !blocoOpcoes) return;

        const show = tipoNova.value === 'opcoes';
        blocoOpcoes.classList.toggle('d-none', !show);
        setOptionsEnabled(blocoOpcoes, show);
    }

    function openNovaPerguntaPropostaModal(button) {
        if (!button) return;
        const form = document.getElementById('formNovaPergunta');
        form.reset();
        document.getElementById('categoriaNovaPergunta').value = button.dataset.novaPerguntaCategoria;
        document.getElementById('categoriaNovaPerguntaNome').textContent = button.dataset.categoriaNome;
        document.getElementById('ordemNovaPergunta').value = button.dataset.proximaOrdem || 0;
        document.getElementById('opcoesContainerNova').replaceChildren(
            createOptionInput('', 'Opção 1'), createOptionInput('', 'Opção 2')
        );
        syncOpcoesNova();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('novaPerguntaPropostaModal')).show();
    }

    function openEditPerguntaPropostaModal(button) {
        const form = document.getElementById('editarPerguntaPropostaForm');
        const tipo = document.getElementById('editar_pergunta_tipo');
        const titulo = document.getElementById('editar_pergunta_titulo');
        const ordem = document.getElementById('editar_pergunta_ordem');
        const blocoOpcoes = document.getElementById('blocoOpcoesEditar');
        const opcoesContainer = document.getElementById('opcoesContainerEditar');
        if (!form || !tipo || !titulo || !ordem || !blocoOpcoes || !opcoesContainer) return;

        const tipoValue = button.dataset.perguntaTipo || '';
        form.action = button.dataset.updateUrl || '';
        document.getElementById('editarPerguntaId').value = button.dataset.perguntaId;
        tipo.value = tipoLabels[tipoValue] || tipoValue;
        titulo.value = button.dataset.perguntaTitulo || '';
        ordem.value = button.dataset.perguntaOrdem || 0;
        document.getElementById('editar_pergunta_categoria').value = button.dataset.perguntaCategoria;
        document.getElementById('editarPerguntaCategoriaNome').textContent = button.dataset.categoriaNome;

        opcoesContainer.innerHTML = '';
        const showOptions = tipoValue === 'opcoes';
        blocoOpcoes.classList.toggle('d-none', !showOptions);

        if (showOptions) {
            let opcoes = [];
            try {
                opcoes = JSON.parse(atob(button.dataset.perguntaOpcoes || 'W10='));
            } catch (error) {
                opcoes = [];
            }

            const normalizedOptions = opcoes.length ? opcoes : ['', ''];
            normalizedOptions.forEach((opcao, index) => {
                opcoesContainer.appendChild(createOptionInput(opcao, `Opção ${index + 1}`, 'form-control mb-2'));
            });
            setOptionsEnabled(blocoOpcoes, true);
        }

        bootstrap.Modal.getOrCreateInstance(document.getElementById('editarPerguntaPropostaModal')).show();
    }

    document.addEventListener('DOMContentLoaded', function () {
        const tipoNova = document.getElementById('tipoNovaPergunta');
        const formNova = document.getElementById('formNovaPergunta');

        tipoNova?.addEventListener('change', syncOpcoesNova);
        formNova?.addEventListener('submit', syncOpcoesNova);
        syncOpcoesNova();

        document.getElementById('novaPerguntaPropostaModal').addEventListener('shown.bs.modal', function () {
            document.getElementById('tituloNovaPergunta').focus();
        });
        document.getElementById('editarPerguntaPropostaModal').addEventListener('shown.bs.modal', function () {
            document.getElementById('editar_pergunta_titulo').focus();
        });

        document.getElementById('btnAddOpcaoNova')?.addEventListener('click', function () {
            document.getElementById('opcoesContainerNova')?.appendChild(createOptionInput());
        });

        document.getElementById('btnAddOpcaoEditar')?.addEventListener('click', function () {
            document.getElementById('opcoesContainerEditar')?.appendChild(createOptionInput());
        });
        @if($errors->any())
            const dadosAnteriores = {{ \Illuminate\Support\Js::from(session()->getOldInput()) }};
            if (dadosAnteriores._form === 'categoria_orcamento') {
                const button = [...document.querySelectorAll('[data-categoria-id]')].find(el => el.dataset.categoriaId === String(dadosAnteriores._categoria_id));
                openCategoriaOrcamentoModal(button);
                document.getElementById('categoriaOrcamentoNome').value = dadosAnteriores.nome || '';
                document.getElementById('categoriaOrcamentoOrdem').value = dadosAnteriores.ordem || 0;
            } else if (['nova_pergunta', 'editar_pergunta'].includes(dadosAnteriores._form)) {
                const editando = dadosAnteriores._form === 'editar_pergunta';
                const button = [...document.querySelectorAll('[data-pergunta-id]')].find(el => el.dataset.perguntaId === String(dadosAnteriores._pergunta_id));
                if (editando && !button) return;
                if (editando) openEditPerguntaPropostaModal(button);
                else {
                    const categoriaButton = [...document.querySelectorAll('[data-nova-pergunta-categoria]')]
                        .find(el => el.dataset.novaPerguntaCategoria === String(dadosAnteriores.id_categoria_orcamento));
                    if (!categoriaButton) return;
                    openNovaPerguntaPropostaModal(categoriaButton);
                }
                const form = document.getElementById(editando ? 'editarPerguntaPropostaForm' : 'formNovaPergunta');
                ['titulo', 'ordem', 'tipo'].forEach(name => {
                    if (form.elements[name] && dadosAnteriores[name] !== undefined) form.elements[name].value = dadosAnteriores[name];
                });
                const container = document.getElementById(editando ? 'opcoesContainerEditar' : 'opcoesContainerNova');
                if (Array.isArray(dadosAnteriores.opcoes)) {
                    container.replaceChildren(...dadosAnteriores.opcoes.map(opcao => createOptionInput(opcao)));
                }
                if (!editando) syncOpcoesNova();
                bootstrap.Modal.getOrCreateInstance(document.getElementById(editando ? 'editarPerguntaPropostaModal' : 'novaPerguntaPropostaModal')).show();
            }
        @endif
    });
</script>
</body>
</html>
