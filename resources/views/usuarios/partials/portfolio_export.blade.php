@auth
    @if($portfolio && Auth::id() === $usuario->id && (int) Auth::user()->tipo_usuario === 2)
        @php
            $exportLinks = \App\Support\PortfolioSocialLinks::forProfile($portfolio, $usuario->telefone);
            $exportGroups = collect($categoriasPortfolio ?? [] )->map(fn ($album) => [
                'id' => $album->id, 'nome' => $album->nome, 'posts' => $portfolio->posts->where('id_categoria_post_portfolio', $album->id),
            ]);
            $exportLoose = $portfolio->posts->filter(fn ($post) => !collect($categoriasPortfolio ?? [])->contains('id', $post->id_categoria_post_portfolio));
            if ($exportLoose->isNotEmpty()) $exportGroups->push(['id' => null, 'nome' => 'Trabalhos avulsos', 'posts' => $exportLoose]);
        @endphp
        <div class="modal fade" id="portfolioExportModal" tabindex="-1" aria-labelledby="portfolioExportModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="portfolioExportModalLabel">Exportar portfólio em pdf</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <form id="portfolioExportForm" action="{{ route('portfolio.exportar-pdf', $portfolio) }}" method="POST" target="_blank" data-portfolio-export>
                        @csrf
                    </form>
                    <div class="modal-body">
                        <fieldset class="portfolio-export-section">
                            <legend>Apresentação</legend>
                            <div class="portfolio-export-fields">
                                @foreach(\App\Support\PortfolioPdf::FIELDS as $key => $label)
                                    @php
                                        $value = match ($key) {
                                            'nome_artistico', 'descricao' => $portfolio->{$key},
                                            'foto' => $usuario->foto_perfil,
                                            'areas' => $usuario->categoriasArtisticas->isNotEmpty(),
                                            default => $usuario->{$key},
                                        };
                                    @endphp
                                    @if($value)
                                        <label><input type="checkbox" name="campos[]" value="{{ $key }}" form="portfolioExportForm" @checked(in_array($key, ['nome', 'foto', 'nome_artistico', 'descricao', 'areas']))><span>{{ $label }}</span></label>
                                    @endif
                                @endforeach
                            </div>
                        </fieldset>
                        @if($exportLinks)
                            <fieldset class="portfolio-export-section">
                                <legend>Redes sociais e contato</legend>
                                <div class="portfolio-export-fields">
                                    @foreach($exportLinks as $link)
                                        <label><input type="checkbox" name="redes[]" value="{{ $link['network'] }}" form="portfolioExportForm" @checked($link['network'] !== 'whatsapp')><span>{{ $link['label'] }}</span></label>
                                    @endforeach
                                </div>
                            </fieldset>
                        @endif
                        <section class="portfolio-export-section" aria-label="Trabalhos para exportar">
                            <div class="portfolio-export-toolbar">
                                <h6>Trabalhos</h6>
                                <div>
                                    <button type="button" class="btn btn-outline-custom btn-sm" data-export-all>Selecionar todos</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" data-export-clear>Limpar</button>
                                </div>
                            </div>
                            @forelse($exportGroups as $group)
                                <fieldset class="portfolio-export-group" data-export-group>
                                    <legend>
                                        <label><input type="checkbox" @if($group['id']) name="albuns[]" value="{{ $group['id'] }}" form="portfolioExportForm" @endif data-export-album><span>{{ $group['nome'] }}</span></label>
                                        <span class="portfolio-export-count">{{ $group['posts']->count() }} {{ $group['posts']->count() === 1 ? 'post' : 'posts' }}</span>
                                    </legend>
                                    @forelse($group['posts'] as $post)
                                        <label class="portfolio-export-post">
                                            <input type="checkbox" name="posts[]" value="{{ $post->id }}" form="portfolioExportForm" data-export-post>
                                            @if($post->imagens->isNotEmpty())
                                                <img src="{{ \App\Support\MediaStorage::publicUrl($post->imagens->first()->caminho_imagem) }}" alt="" loading="lazy">
                                            @else
                                                <span class="portfolio-export-placeholder"><i class="bi bi-image" aria-hidden="true"></i></span>
                                            @endif
                                            <span class="portfolio-export-post-text"><strong>{{ $post->nome }}</strong><small>{{ $post->imagens->count() }} {{ $post->imagens->count() === 1 ? 'imagem' : 'imagens' }}</small></span>
                                        </label>
                                    @empty
                                        <p class="text-muted small mb-0">Nenhum post neste álbum ainda.</p>
                                    @endforelse
                                </fieldset>
                            @empty
                                <p class="text-muted small">Nenhum trabalho cadastrado ainda.</p>
                            @endforelse
                        </section>
                        <p class="portfolio-export-status" data-export-status aria-live="polite"></p>
                        <div class="alert alert-danger" data-export-error role="alert" hidden></div>
                        <a class="btn btn-outline-custom" data-export-download target="_blank" rel="noopener" hidden><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> Abrir PDF</a>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" form="portfolioExportForm" class="btn btn-primary-custom" data-export-submit><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> Gerar PDF</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endauth
