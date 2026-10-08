@auth
    @if(Auth::id() === $usuario->id && (int) Auth::user()->tipo_usuario === 2)
        <div class="modal fade" id="editModalportfolio" tabindex="-1" aria-labelledby="editModalportfolioLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editModalportfolioLabel">{{ $portfolio ? 'Editar portfólio' : 'Criar portfólio' }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <form id="portfolioSettingsForm" action="{{ $portfolio ? route('portfolio.update', $portfolio->id) : route('portfolio.store') }}" method="POST">
                        @csrf
                        @if($portfolio)
                            @method('PUT')
                        @endif
                        <input type="hidden" name="categorias_form" value="1">
                    </form>
                    <div class="modal-body">
                        @php
                            $portfolioErrors = collect(['nome_artistico', 'descricao', 'link_instagram', 'link_behance', 'link_tiktok', 'link_github', 'link_linkedin', 'cor_primaria_portfolio', 'cor_secundaria_portfolio', 'estilo_card_categorias_portfolio', 'categorias', 'categorias.*'])->filter(fn ($field) => $errors->has($field));
                            $portfolioAreasSelecionadas = session()->hasOldInput('categorias_form') ? old('categorias', []) : $categoriasSelecionadas;
                            $portfolioAreasSelecionadas = is_array($portfolioAreasSelecionadas) ? array_filter($portfolioAreasSelecionadas, fn ($id) => is_int($id) || is_string($id)) : [];
                        @endphp
                        @if($portfolioErrors->isNotEmpty())
                            <div class="alert alert-danger" data-portfolio-errors="{{ $portfolioErrors->first() }}">
                                @foreach($portfolioErrors as $field)
                                    <div>{{ $errors->first($field) }}</div>
                                @endforeach
                            </div>
                        @endif
                        <ul class="nav nav-tabs portfolio-settings-tabs mb-4" role="tablist" aria-label="Configurações do portfólio">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="portfolio-general-tab" data-bs-toggle="tab" data-bs-target="#portfolio-general-pane" type="button" role="tab" aria-controls="portfolio-general-pane" aria-selected="true">Informações gerais</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="portfolio-categories-tab" data-bs-toggle="tab" data-bs-target="#portfolio-categories-pane" type="button" role="tab" aria-controls="portfolio-categories-pane" aria-selected="false">Cadastrar álbuns</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="portfolio-style-tab" data-bs-toggle="tab" data-bs-target="#portfolio-style-pane" type="button" role="tab" aria-controls="portfolio-style-pane" aria-selected="false">Estilo</button>
                            </li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="portfolio-general-pane" role="tabpanel" aria-labelledby="portfolio-general-tab" tabindex="0">
                                <div class="mb-3">
                                    <label for="portfolio_nome_artistico" class="form-label">Nome artístico</label>
                                    <input id="portfolio_nome_artistico" form="portfolioSettingsForm" type="text" name="nome_artistico" class="form-control" maxlength="255" value="{{ old('nome_artistico', $portfolio->nome_artistico ?? '') }}">
                                </div>
                                <div class="mb-3">
                                    <label for="portfolio_descricao" class="form-label">Descrição</label>
                                    <textarea id="portfolio_descricao" form="portfolioSettingsForm" name="descricao" class="form-control" rows="3">{{ old('descricao', $portfolio->descricao ?? '') }}</textarea>
                                </div>
                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label for="portfolio_link_instagram" class="form-label">Link do Instagram</label>
                                        <input id="portfolio_link_instagram" form="portfolioSettingsForm" type="url" name="link_instagram" class="form-control" maxlength="2000" placeholder="https://instagram.com/seu-perfil" value="{{ old('link_instagram', $portfolio->link_instagram ?? '') }}">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="portfolio_link_pessoal" class="form-label">Link pessoal</label>
                                        <input id="portfolio_link_pessoal" form="portfolioSettingsForm" type="url" name="link_behance" class="form-control" maxlength="2000" placeholder="https://seu-site.com" value="{{ old('link_behance', $portfolio->link_behance ?? '') }}">
                                    </div>
                                    @foreach(['tiktok' => 'TikTok', 'github' => 'GitHub', 'linkedin' => 'LinkedIn'] as $network => $label)
                                        <div class="col-md-6">
                                            <label for="portfolio_link_{{ $network }}" class="form-label">Link do {{ $label }}</label>
                                            <input id="portfolio_link_{{ $network }}" form="portfolioSettingsForm" type="url" name="link_{{ $network }}" class="form-control" maxlength="2000" placeholder="https://{{ $network }}.com/seu-perfil" value="{{ old('link_'.$network, $portfolio?->{'link_'.$network} ?? '') }}">
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            <div class="tab-pane fade" id="portfolio-categories-pane" role="tabpanel" aria-labelledby="portfolio-categories-tab" tabindex="0">
                                @include('usuarios.partials.areas_atuacao_picker')
                                @if($portfolio)
                                    <div class="perfil-categorias-toolbar">
                                        <h6 class="mb-0">Álbuns</h6>
                                        <button type="button" class="btn btn-outline-custom btn-sm perfil-categoria-icon-btn" onclick="openNovaCategoriaPortfolioModal()" aria-label="Adicionar álbum" title="Adicionar álbum">
                                            <i class="bi bi-plus-lg"></i>
                                        </button>
                                    </div>
                                    
                                    <div class="perfil-categorias-list">
                                        <div class="perfil-categorias-list-head">
                                            <span>Ordem</span>
                                            <span>Nome do álbum</span>
                                            <span>Ações</span>
                                        </div>
                                        @forelse(($categoriasPortfolio ?? collect()) as $cat)
                                            <div class="perfil-categorias-list-row">
                                                <span class="perfil-categorias-list-order">{{ $cat->ordem }}</span>
                                                <span class="perfil-categorias-list-name portfolio-category-name">@include('usuarios.partials.categoria_icone', ['icone' => $cat->icone])<span>{{ $cat->nome }}</span></span>
                                                <span class="perfil-categorias-list-actions">
                                                    <button
                                                        type="button"
                                                        class="btn btn-outline-custom btn-sm perfil-categoria-icon-btn"
                                                        data-update-url="{{ route('categorias-posts-portfolio.update', $cat) }}"
                                                        data-categoria-nome="{{ $cat->nome }}"
                                                        data-categoria-ordem="{{ $cat->ordem }}"
                                                        data-categoria-descricao="{{ $cat->descricao }}"
                                                        data-categoria-icone="{{ $cat->icone }}"
                                                        onclick="openEditCategoriaPortfolioModal(this)"
                                                        aria-label="Editar álbum {{ $cat->nome }}"
                                                    >
                                                        <i class="bi bi-journal-text"></i>
                                                    </button>
                                                    <form action="{{ route('categorias-posts-portfolio.destroy', $cat) }}" method="POST" class="d-inline" onsubmit="return confirm('Excluir este álbum? Os posts ficam sem álbum.');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-outline-danger btn-sm perfil-categoria-icon-btn" aria-label="Excluir álbum {{ $cat->nome }}">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                </span>
                                            </div>
                                        @empty
                                            <div class="perfil-categorias-empty">
                                                Nenhum álbum cadastrado ainda.
                                            </div>
                                        @endforelse
                                    </div>
                                @else
                                    <p class="text-muted mb-0">Salve o portfólio para cadastrar seus álbuns.</p>
                                @endif
                            </div>
                            <div class="tab-pane fade" id="portfolio-style-pane" role="tabpanel" aria-labelledby="portfolio-style-tab" tabindex="0">
                                <div class="portfolio-colors mb-4">
                                    <div>
                                        <label for="cor_primaria_portfolio" class="form-label">Cor primária</label>
                                        <input form="portfolioSettingsForm" type="color" name="cor_primaria_portfolio" id="cor_primaria_portfolio" class="form-control form-control-color" value="{{ old('cor_primaria_portfolio', $corPrimariaPortfolio) }}" data-portfolio-color="--roxo-appolo">
                                    </div>
                                    <div>
                                        <label for="cor_secundaria_portfolio" class="form-label">Cor secundária</label>
                                        <input form="portfolioSettingsForm" type="color" name="cor_secundaria_portfolio" id="cor_secundaria_portfolio" class="form-control form-control-color" value="{{ old('cor_secundaria_portfolio', $corSecundariaPortfolio) }}" data-portfolio-color="--roxo-claro">
                                    </div>
                                    <button type="button" class="portfolio-color-reset" data-portfolio-color-reset data-primary-default="{{ $visualSistema->corBase() }}" data-secondary-default="{{ $visualSistema->corSecundaria() }}" title="Restaurar cores padrão" aria-label="Restaurar cores padrão"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i></button>
                                </div>
                                @php
                                    $previewEstiloImages = $portfolio ? $portfolio->posts->flatMap(fn ($post) => $post->imagens)->take(3)->values() : collect();
                                @endphp
                                <div class="perfil-estilos-grid">
                                    @foreach($estilosCardsCategorias as $estiloId => $estilo)
                                        <label class="perfil-estilo-option" for="estilo_card_categoria_{{ $estiloId }}">
                                            <input
                                                class="form-check-input"
                                                type="radio"
                                                aria-label="{{ $estilo['nome'] }}"
                                                name="estilo_card_categorias_portfolio"
                                                form="portfolioSettingsForm"
                                                id="estilo_card_categoria_{{ $estiloId }}"
                                                value="{{ $estiloId }}"
                                                {{ (int) old('estilo_card_categorias_portfolio', $estiloCardCategorias) === $estiloId ? 'checked' : '' }}
                                            >
                                            <span class="perfil-estilo-preview-wrap">
                                                <span class="perfil-categoria-card perfil-categoria-card-estilo-{{ $estiloId }} perfil-categoria-card-preview">
                                                    @include('usuarios.partials.categoria_card_conteudo', ['estiloId' => $estiloId, 'imagens' => $previewEstiloImages, 'titulo' => $estilo['nome'], 'icone' => 'bi-palette', 'descricao' => $estilo['descricao']])
                                                </span>
                                            </span>
                                            <span class="perfil-estilo-option-text">
                                                <strong>{{ $estilo['nome'] }}</strong>
                                                <small>{{ $estilo['descricao'] }}</small>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" form="portfolioSettingsForm" class="btn btn-outline-custom">{{ $portfolio ? 'Salvar alterações' : 'Criar portfólio' }}</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endauth
