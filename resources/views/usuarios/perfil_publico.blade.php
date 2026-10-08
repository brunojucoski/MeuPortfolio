<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Perfil Público</title>
    <link href="{{ asset('css/perfil.css') }}" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/portfolio-cards.css') }}" rel="stylesheet">
    <link href="{{ asset('css/portfolio-profile-details.css') }}" rel="stylesheet">
    <link href="{{ asset('css/portfolio-areas.css') }}" rel="stylesheet">
</head>
@php
    $portfolio = $usuario->portfolioArtista;
    $categoriasOrcamentoDisponiveis = $portfolio
        ? $portfolio->categoriasOrcamento->filter(fn ($categoria) => $categoria->perguntas_count > 0)->values()
        : collect();
    $corPrimariaPortfolio = preg_match('/^#[0-9A-Fa-f]{6}$/', (string) ($portfolio->cor_primaria_portfolio ?? ''))
        ? $portfolio->cor_primaria_portfolio
        : $visualSistema->corBase();
    $corSecundariaPortfolio = preg_match('/^#[0-9A-Fa-f]{6}$/', (string) ($portfolio->cor_secundaria_portfolio ?? ''))
        ? $portfolio->cor_secundaria_portfolio
        : $visualSistema->corSecundaria();
    $contrasteBotaoOrcamento = static function (string $cor): string {
        $canais = array_map(static function ($canal) {
            $valor = $canal / 255;
            return $valor <= .04045 ? $valor / 12.92 : (($valor + .055) / 1.055) ** 2.4;
        }, sscanf($cor, '#%02x%02x%02x'));
        $luminancia = .2126 * $canais[0] + .7152 * $canais[1] + .0722 * $canais[2];
        return $luminancia > .179 ? '#000000' : '#ffffff';
    };
    $estilosCardsCategorias = \App\Models\PortfolioArtista::estilosCardsCategorias();
    $estiloCardCategorias = (int) ($portfolio->estilo_card_categorias_portfolio ?? \App\Models\PortfolioArtista::ESTILO_CARD_CATEGORIA_3D);
    $estiloCardCategorias = array_key_exists($estiloCardCategorias, $estilosCardsCategorias)
        ? $estiloCardCategorias
        : \App\Models\PortfolioArtista::ESTILO_CARD_CATEGORIA_3D;
@endphp
<body style="--roxo-appolo: {{ $corPrimariaPortfolio }}; --roxo-claro: {{ $corSecundariaPortfolio }};">
@include('Components.navbarbootstrap')

<main class="perfil-publico-page">

    {{-- Funções de formatação (mantidas) --}}
    @php
        function formatarCep($cep) {
            return preg_replace('/(\d{5})(\d{3})/', '$1-$2', $cep);
        }
        function formatarTelefone($tel) {
            $tel = preg_replace('/\D/', '', $tel);
            return (strlen($tel) === 11)
                ? preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $tel)
                : preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $tel);
        }
    @endphp

  
    {{-- Modais de Erro/Sucesso --}}
@if(session('success') && !session('prompt_whatsapp_proposta'))
    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="text-nome" id="successModalLabel"> APPOLO </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    {{ session('success') }}
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-custom" data-bs-dismiss="modal"> Fechar </button>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // ----- CÓDIGO ADICIONAL PARA FORÇAR A LIMPEZA DO BOOTSTRAP -----
            // 1. Garante que a classe 'modal-open' seja removida do body
            document.body.classList.remove('modal-open');

            // 2. Remove qualquer backdrop de modal que possa ter ficado preso
            const existingBackdrops = document.querySelectorAll('.modal-backdrop');
            existingBackdrops.forEach(backdrop => backdrop.remove());

            // 3. Opcional, mas seguro: Força o 'hide' em qualquer modal 'show'
            document.querySelectorAll('.modal.show').forEach(openModal => {
                const modalInstance = bootstrap.Modal.getInstance(openModal);
                if (modalInstance) {
                    modalInstance.hide();
                }
            });
            // ----- FIM DO CÓDIGO DE LIMPEZA -----

            var successModal = new bootstrap.Modal(document.getElementById('successModal'));
            successModal.show();
        });
    </script>
@endif

@if(session('prompt_whatsapp_proposta') && session('whatsapp_proposta_url'))
    <div class="modal fade" id="modalWhatsappPosProposta" tabindex="-1" aria-labelledby="modalWhatsappPosPropostaLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="text-nome" id="modalWhatsappPosPropostaLabel">Proposta enviada</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <p class="text-success small mb-2">Proposta enviada com sucesso!</p>
                    <p class="mb-0">Deseja também informar ao profissional via WhatsApp sobre o seu orçamento enviado?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-custom" id="btnWhatsappPropostaSim">Sim</button>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Não</button>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var url = @json(session('whatsapp_proposta_url'));
            var el = document.getElementById('modalWhatsappPosProposta');
            if (!el || !url) return;
            var modal = new bootstrap.Modal(el);
            modal.show();
            document.getElementById('btnWhatsappPropostaSim').addEventListener('click', function () {
                window.open(url, '_blank');
                modal.hide();
            });
        });
    </script>
@endif

@if(session('error'))
    <div class="container mt-3">
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
@endif


    {{-- 1. SEÇÃO PRINCIPAL DE INFORMAÇÕES DO PERFIL (TOPO DA PÁGINA) --}}
    <div class="p-3 perfil-header-wrap">
        <section class="py-5 perfil-header-section">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-md-3 text-center text-md-start mb-4 mb-md-0 imagem_perfil">
                        <img src="{{ $usuario->foto_perfil && file_exists(public_path('storage/' . $usuario->foto_perfil)) ? asset('storage/' . $usuario->foto_perfil) : asset('imgs/user.png') }}" class="rounded-circle  profile-img" alt="Perfil">
                    </div>
                    <div class="col-md-9">
                        <h1 class="text-nome perfil-header-name">{{ $usuario->nome }} </h1>

                        @if($usuario->tipo_usuario == 2)
                            <h3 class="text-nome perfil-header-artist"> {{ $portfolio->nome_artistico ?? '' }} </h3>
                        @endif

                        <div class="d-none d-lg-block perfil-dados-resumo">
                            <p class="text-muted mb-1"><i class="bi bi-calendar"></i> {{ $usuario->idade }} anos </p>
                            <p class="text-muted mb-1"><i class="bi bi-geo-alt"></i> {{ $usuario->cidade ?? 'Localidade não definida' }} </p>
                            <p class="text-muted mb-1"><i class="bi bi-telephone"></i> {{ formatarTelefone($usuario->telefone) }}</p>
                            <p class="mb-2"><strong>Endereço:</strong> {{ formatarCep($usuario->cep) }} , {{ $usuario->bairro }} , {{ $usuario->endereco }}</p>
                            @if($usuario->tipo_usuario == 2)
                                <p class="mb-2"><i class="bi bi-brush"></i> {{ $portfolio->descricao ?? 'Descrição do portfólio não disponível' }}</p>
                            @endif
                        </div>

                        <div class="d-lg-none perfil-mais-info-mobile mb-2">
                            <button class="btn btn-custom w-100 d-flex justify-content-between align-items-center perfil-mais-info-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#maisInformacoesPerfil" aria-expanded="false" aria-controls="maisInformacoesPerfil">
                                <span>Mais informações</span>
                                <i class="bi bi-chevron-down"></i>
                            </button>
                            <div class="collapse perfil-mais-info-collapse mt-2" id="maisInformacoesPerfil">
                                <div class="perfil-mais-info-inner border rounded-3 p-3">
                                    <p class="text-muted mb-2"><i class="bi bi-calendar"></i> {{ $usuario->idade }} anos </p>
                                    <p class="text-muted mb-2"><i class="bi bi-geo-alt"></i> {{ $usuario->cidade ?? 'Localidade não definida' }} </p>
                                    <p class="text-muted mb-2"><i class="bi bi-telephone"></i> {{ formatarTelefone($usuario->telefone) }}</p>
                                    <p class="mb-2"><strong>Endereço:</strong> {{ formatarCep($usuario->cep) }} , {{ $usuario->bairro }} , {{ $usuario->endereco }}</p>
                                    @if($usuario->tipo_usuario == 2)
                                        <p class="mb-0"><i class="bi bi-brush"></i> {{ $portfolio->descricao ?? 'Descrição do portfólio não disponível' }}</p>
                                    @endif
                                </div>
                            </div>
                        </div>

                            <div class="social-icons my-3">
                                @include('usuarios.partials.social_links')
                                @if($usuario->tipo_usuario == 2)
                                {{-- DIV DA MÉDIA DE AVALIAÇÕES --}}
                                <div class="mt-1 perfil-header-rating">
                                    @php
                                        $feedbacks = $feedbacksParaMedia;
                                        $media = $feedbacks->avg('nota');
                                    @endphp

                                    @if($media)
                                        <strong>{{ number_format($media, 1) }}</strong> ⭐ ({{ $feedbacks->count() }} avaliação{{ $feedbacks->count() > 1 ? 's' : '' }})
                                    @else
                                        <em>Sem avaliações ainda</em>
                                    @endif
                                </div>
                                @endif
                            </div>

                        <div class="perfil-orcamento-actions" style="--orcamento-texto: {{ $contrasteBotaoOrcamento($corPrimariaPortfolio) }}; --orcamento-texto-hover: {{ $contrasteBotaoOrcamento($corSecundariaPortfolio) }};">
                        @guest
                            @if($usuario->tipo_usuario == 2 && $usuario->portfolioArtista && $usuario->portfolioArtista->perguntasPropostaContrato->count() > 0)
                                <button type="button" class="btn btn-primary-custom perfil-orcamento-button" data-bs-toggle="modal" data-bs-target="#modalConviteCadastroSolicitante">
                                    <i class="bi bi-send" aria-hidden="true"></i><span>Enviar orçamento</span>
                                </button>
                               
                            @else
                             
                            @endif
                        @else
                            @if(Auth::user()->tipo_usuario == 3 && $usuario->tipo_usuario == 2)
                                @if($usuario->portfolioArtista && $usuario->portfolioArtista->perguntasPropostaContrato->count() > 0)
                                    <button type="button" class="btn btn-primary-custom perfil-orcamento-button" data-bs-toggle="modal" data-bs-target="#modalPropostaContrato">
                                        <i class="bi bi-send" aria-hidden="true"></i><span>Enviar orçamento</span>
                                    </button>
                                @elseif($usuario->portfolioArtista)
                                    <button class="btn btn-primary-custom perfil-orcamento-button" type="button" disabled title="Este artista ainda não configurou o formulário de proposta.">
                                        <i class="bi bi-file-earmark-lock" aria-hidden="true"></i><span>Orçamento indisponível</span>
                                    </button>
                                @else
                                    <button class="btn btn-primary-custom perfil-orcamento-button" type="button" disabled>
                                        <i class="bi bi-file-earmark-lock" aria-hidden="true"></i><span>Artista com cadastro incompleto</span>
                                    </button>
                                @endif
                            @endif
                        @endguest
                        </div>

                        @auth
                            @if(auth()->user()->id === $usuario->id && auth()->user()->tipo_usuario == 2)
                                <button class="btn btn-outline-custom" data-bs-toggle="modal" data-bs-target="#editModalportfolio">
                                    <i class="bi bi-pencil"></i> {{ $portfolio ? 'Editar Portfólio' : 'Criar Portfólio' }}
                                </button>
                                @if($portfolio)
                                    <button type="button" class="btn btn-primary-custom ms-1" data-portfolio-new-post data-bs-toggle="modal" data-bs-target="#postModal">
                                        <i class="bi bi-plus-circle" aria-hidden="true"></i> Post
                                    </button>
                                @endif
                            @endif
                        @endauth

                        {{-- Categorias (mantidas) --}}
                        @if($usuario->tipo_usuario == 2)
                            @if($usuario->categoriasArtisticas && $usuario->categoriasArtisticas->count() > 0)
                                <div class="mb-3 p-3 perfil-areas-atuacao">
                                    <p class="form-label mb-3">Áreas de atuação</p>
                                    <div class="d-flex flex-wrap gap-2">
                                        @foreach ($usuario->categoriasArtisticas as $cat)
                                            <span class="portfolio-area-tag">{{ $cat->nome }}</span>
                                        @endforeach
                                    </div>
                                </div>
                            @else
                                <p class="text-muted">Nenhuma área de atuação selecionada</p>
                            @endif
                        @endif
                    </div> {{-- Fim col-md-9 --}}
                </div> {{-- Fim row --}}
            </div> {{-- Fim container --}}
        </section> {{-- Fim section.py-5 --}}
    </div> {{-- Fim p-3 --}}


    {{-- 2. SEÇÃO DE PORTFÓLIO (CONDICIONAL: APENAS PARA ARTISTAS) --}}
    @if($usuario->tipo_usuario == 2)
        @php
            $posts = $posts ?? collect();
            $categoriasPortfolio = $categoriasPortfolio ?? collect();
            $categoriaAtiva = $categoriaAtiva ?? null;
            $totalPostsPortfolio = $portfolio ? $portfolio->posts->count() : 0;
            $exibirBlocoPortfolio = $portfolio && $usuario->tipo_usuario == 2;
        @endphp
        @if($exibirBlocoPortfolio)
            <div id="portfolio-navigation" data-active-category="{{ $categoriaAtiva?->id ?? '' }}">
            <section id="portfolio-posts" class="bg-light perfil-posts-section" aria-label="Posts do portfólio" @if(!$categoriaAtiva && $posts->isEmpty()) hidden @endif>
                <div class="container">
                    <a href="{{ route('usuarios.perfilPublico', $usuario->id) }}#portfolio-categorias" class="portfolio-back text-simples" data-portfolio-back @if(!$categoriaAtiva) hidden @endif><i class="bi bi-arrow-left" aria-hidden="true"></i> Álbuns</a>
                    <div class="portfolio-posts-heading mb-3">
                    <h4 class="text-nome h5 text-center mb-0 portfolio-current-title" tabindex="-1" data-portfolio-title>
                        <span data-portfolio-title-icon>@include('usuarios.partials.categoria_icone', ['icone' => $categoriaAtiva?->icone])</span>
                        <span data-portfolio-title-text>{{ $categoriaAtiva?->nome ?? 'Posts' }}</span>
                    </h4>
                    @auth
                        @if(auth()->user()->id === $usuario->id && auth()->user()->tipo_usuario == 2)
                            <button type="button" class="btn btn-primary-custom" data-portfolio-new-post data-bs-toggle="modal" data-bs-target="#postModal">
                                <i class="bi bi-plus-circle" aria-hidden="true"></i> Post
                            </button>
                        @endif
                    @endauth
                    </div>
                    <p class="portfolio-category-description text-muted text-center" data-portfolio-description @if(!$categoriaAtiva?->descricao) hidden @endif>{{ $categoriaAtiva?->descricao }}</p>
                    <p class="visually-hidden" aria-live="polite" data-portfolio-status></p>
                    <div class="row g-4">
                        @foreach($portfolio->posts as $post)
                            <div class="col-md-4" data-portfolio-post data-post-category="{{ $post->id_categoria_post_portfolio ?? '' }}" @if(!$posts->contains('id', $post->id)) hidden @endif>
                                <div class="perfil-post-card">
                                    <div id="carouselPost{{ $post->id }}" class="carousel slide" data-bs-ride="carousel">
                                        <div class="carousel-inner">
                                            @foreach($post->imagens as $index => $img)
                                                <div class="carousel-item {{ $index === 0 ? 'active' : '' }}">
                                                    <img src="{{ asset('storage/' . $img->caminho_imagem) }}" alt="Imagem do post" class="d-block w-100 rounded gallery-img"
                                                         data-bs-toggle="modal" data-bs-target="#modalPost{{ $post->id }}">
                                                </div>
                                            @endforeach
                                        </div>
                                        @if(count($post->imagens) > 1)
                                            <button class="carousel-control-prev" type="button" data-bs-target="#carouselPost{{ $post->id }}" data-bs-slide="prev">
                                                <span class="carousel-control-prev-icon"></span>
                                            </button>
                                            <button class="carousel-control-next" type="button" data-bs-target="#carouselPost{{ $post->id }}" data-bs-slide="next">
                                                <span class="carousel-control-next-icon"></span>
                                            </button>
                                        @endif
                                    </div>
                                    <button type="button" class="perfil-post-title" data-bs-toggle="modal" data-bs-target="#modalPost{{ $post->id }}">{{ $post->nome }}</button>
                                </div>
                            </div>
                            {{-- MODAL INDIVIDUAL PARA CADA POST (DEFINIDA AQUI DENTRO DO FOREACH) --}}
                           <div class="modal fade" id="modalPost{{ $post->id }}" tabindex="-1" aria-hidden="true">
                              <div class="modal-dialog modal-xl modal-dialog-centered">
                                  <div class="modal-content">
                                      <div class="modal-header border-0">
                                          <div class="d-flex w-100 justify-content-end gap-2">
                                              @auth
                                                  @if(Auth::user()->tipo_usuario == 2 && Auth::id() === $usuario->id)
                                                      {{-- BOTÃO EDITAR: AGORA ABRE A MODAL DE EDIÇÃO --}}
                                                      <button type="button" class="btn btn-outline-custom btn-sm"
                                                              data-bs-toggle="modal"
                                                              data-bs-target="#editPostModal"
                                                              data-post-id="{{ $post->id }}"
                                                              data-post-nome="{{ $post->nome }}"
                                                              data-post-descricao="{{ $post->descricao }}"
                                                              data-post-imagens="{{ $post->imagens->map(fn($img) => ['id' => $img->id, 'caminho' => asset('storage/' . $img->caminho_imagem)])->toJson() }}" 
                                                              data-post-categoria="{{ $post->id_categoria_post_portfolio ?? '' }}"
                                                              onclick="openEditPostModal(this)">
                                                          <i class="bi bi-pencil"></i> Editar
                                                      </button>

                                                      {{-- BOTÃO APAGAR: AGORA ABRE A MODAL DE CONFIRMAÇÃO DE EXCLUSÃO --}}
                                                      <button type="button" class="btn btn-outline-custom btn-sm"
                                                              data-bs-toggle="modal"
                                                              data-bs-target="#deletePostConfirmModal" {{-- ID da nova modal de confirmação --}}
                                                              data-post-id="{{ $post->id }}"
                                                              onclick="openDeletePostConfirmModal(this)"> {{-- Chama a função JS --}}
                                                          <i class="bi bi-trash"></i> Apagar
                                                      </button>
                                                  @endif
                                              @endauth
                                              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                          </div>
                                      </div>
                                        <div class="modal-body">
                                            <div class="row g-4">
                                                <div class="col-lg-8">
                                                    <div id="carouselModal{{ $post->id }}" class="carousel slide" data-bs-ride="carousel">
                                                        <div class="carousel-inner">
                                                            @foreach($post->imagens as $index => $img)
                                                                <div class="carousel-item {{ $index === 0 ? 'active' : '' }}">
                                                                    <img src="{{ asset('storage/' . $img->caminho_imagem) }}" class="d-block w-100 rounded" alt="Imagem Modal">
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                        @if(count($post->imagens) > 1)
                                                            <button class="carousel-control-prev" type="button" data-bs-target="#carouselModal{{ $post->id }}" data-bs-slide="prev">
                                                                <span class="carousel-control-prev-icon"></span>
                                                            </button>
                                                            <button class="carousel-control-next" type="button" data-bs-target="#carouselModal{{ $post->id }}" data-bs-slide="next">
                                                                <span class="carousel-control-next-icon"></span>
                                                            </button>
                                                        @endif
                                                    </div>
                                                </div>
                                                <div class="col-lg-4">
                                                    <div class="d-flex align-items-center mb-4">
                                                        <img src="{{ $usuario->foto_perfil ? asset('storage/' . $usuario->foto_perfil) : asset('imgs/user.png') }}" class="rounded-circle me-3" width="60" height="60" alt="Avatar">
                                                        <div>
                                                            <h5 class="mb-0 text-primary">{{ $post->nome }}</h5>
                                                            <small class="text-muted">{{ $usuario->idade }} anos | {{ $usuario->cidade ?? 'Localidade não definida' }}</small>
                                                        </div>
                                                    </div>
                                                    <div class="bg-light p-3 rounded">
                                                        <p>{{ $post->descricao }}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div> {{-- Fim row g-4 --}}
                    <p class="text-center text-muted my-4" data-portfolio-empty @if($posts->isNotEmpty()) hidden @endif>Nenhum post neste álbum ainda.</p>
                </div> {{-- Fim container --}}
            </section> {{-- Fim section.py-4 bg-light --}}
            @if($categoriasPortfolio->isNotEmpty())
                <section id="portfolio-categorias" class="py-2 perfil-portfolio-categorias-section" @if($categoriaAtiva) hidden @endif>
                    <div class="align-itens-center text-center perfil-portfolio-header">
                        <h3 class="text-nome mb-0">Portfólio</h3>
                    </div>
                    <div class="container">
                        <div class="row g-4">
                            @foreach($categoriasPortfolio as $cat)
                                @php
                                    $previewImages = $portfolio->posts
                                        ->where('id_categoria_post_portfolio', $cat->id)
                                        ->flatMap(fn ($post) => $post->imagens)
                                        ->take(3)
                                        ->values();
                                @endphp
                                <div class="col-md-4 perfil-categoria-card-col">
                                    <a href="{{ route('usuarios.perfilPublico', ['id' => $usuario->id, 'categoria' => $cat->id]) }}#portfolio-posts"
                                       class="perfil-categoria-card perfil-categoria-card-estilo-{{ $estiloCardCategorias }}"
                                       data-portfolio-category="{{ $cat->id }}"
                                       data-category-name="{{ $cat->nome }}"
                                       data-category-description="{{ $cat->descricao }}"
                                       aria-label="Ver álbum {{ $cat->nome }}">
                                        @include('usuarios.partials.categoria_card_conteudo', ['estiloId' => $estiloCardCategorias, 'imagens' => $previewImages, 'titulo' => $cat->nome, 'icone' => $cat->icone, 'descricao' => \Illuminate\Support\Str::limit($cat->descricao ?: 'Ver posts deste álbum', 90)])
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </section>
            @endif
            </div>

            @auth
                @if(Auth::user()->id === $usuario->id && Auth::user()->tipo_usuario == 2 && $portfolio && $totalPostsPortfolio === 0)
                    <div class="container my-5">
                        <div class="col-12 text-center">
                            <div class="card shadow-sm p-4">
                                <h4 class="mb-3">Você ainda não tem posts</h4>
                                <p class="text-muted">Comece a compartilhar seu trabalho com o mundo!</p>
                                <button class="btn btn-outline-custom" data-portfolio-new-post data-bs-toggle="modal" data-bs-target="#postModal">
                                    <i class="bi bi-plus-circle"></i> Faça seu primeiro post
                                </button>
                            </div>
                        </div>
                    </div>
                @endif
            @endauth
        @endif
    @endif {{-- FIM DA SEÇÃO CONDICIONAL DE PORTFÓLIO --}}


    {{-- 3. SEÇÃO DE ÚLTIMAS AVALIAÇÕES (SEMPRE VISÍVEL PARA AMBOS OS TIPOS DE USUÁRIO) --}}
    <section class="container my-5">
        <div class="p-3 align-itens-center text-center">
            <h3 class="text-nome"> Últimas Avaliações </h3>
        </div>
        @if($feedbacksParaLista->isEmpty())
            <p class="text-center text-muted">Ainda não há avaliações para este perfil.</p>
        @else
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                @foreach($feedbacksParaLista as $feedback)
                    <div class="col">
                        <div class="card h-100 shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title mb-2">
                                    @if($feedback->avaliador)
                                        Avaliado por: <span class="fw-bold">{{ $feedback->avaliador->nome }}</span>
                                    @else
                                        Avaliado por: <span class="text-muted">Usuário Removido</span>
                                    @endif
                                </h5>
                                <div class="mb-3">
                                    @for ($i = 1; $i <= 5; $i++)
                                        <i class="bi bi-star{{ $i <= $feedback->nota ? '-fill text-warning' : '' }} fs-5"></i>
                                    @endfor
                                </div>
                                <p class="card-text">{{ $feedback->comentario }}</p>
                            </div>
                            <div class="card-footer bg-white border-0">
                                <small class="text-muted">Avaliado em: {{ $feedback->created_at->format('d/m/Y H:i') }}</small>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>


    {{-- 4. MODAIS GERAIS  --}}
    @if($usuario->portfolioArtista && $usuario->portfolioArtista->perguntasPropostaContrato->count() > 0)
        {{-- Modal: cadastro solicitante (visitantes) --}}
        <div class="modal fade" id="modalConviteCadastroSolicitante" tabindex="-1" aria-labelledby="modalConviteCadastroSolicitanteLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title text-nome" id="modalConviteCadastroSolicitanteLabel">Cadastro na plataforma</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-0">Para acompanhar melhor seu orçamento e possibilitar avaliar o trabalho do profissional a ser contratado gostaria de se cadastrar na plataforma?</p>
                    </div>
                    <div class="modal-footer flex-wrap gap-2">
                        <a href="{{ route('usuarios.cadastro', ['tipo' => 'solicitante', 'orcamento_artista' => $usuario->id]) }}" class="btn btn-outline-custom">Sim</a>
                        <button type="button" class="btn btn-outline-secondary" id="btnConviteCadastroNao">Não</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Modal Proposta de Contrato --}}
        <div class="modal fade modal_proposta" id="modalPropostaContrato" tabindex="-1" role="dialog" aria-labelledby="modalPropostaContratoLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
                <form action="{{ route('propostas.store') }}" method="POST" enctype="multipart/form-data" id="formOrcamentoCategorias" class="w-100" data-validation-error="{{ $errors->any() && (int) old('id_artista') === (int) $portfolio->id ? 'true' : 'false' }}">
                    @csrf
                    <input type="hidden" name="id_artista" value="{{ $usuario->portfolioArtista->id }}">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalPropostaContratoLabel">Enviar proposta</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                        </div>
                        <div class="modal-body">
                            @if($categoriasOrcamentoDisponiveis->count() > 1)
                                <label for="categoriaSolicitacaoOrcamento" class="form-label">Selecione a categoria de orçamento</label>
                                <select name="id_categoria_orcamento" id="categoriaSolicitacaoOrcamento" class="form-select mb-4" required>
                                    <option value="">Selecione uma categoria</option>
                                    @foreach($categoriasOrcamentoDisponiveis as $categoria)
                                        <option value="{{ $categoria->id }}" @selected((string) old('id_categoria_orcamento') === (string) $categoria->id)>{{ $categoria->nome }}</option>
                                    @endforeach
                                </select>
                            @else
                                <input type="hidden" name="id_categoria_orcamento" id="categoriaSolicitacaoOrcamento" value="{{ $categoriasOrcamentoDisponiveis->first()?->id }}">
                                <p class="fw-semibold">{{ $categoriasOrcamentoDisponiveis->first()?->nome }}</p>
                            @endif
                            @foreach($usuario->portfolioArtista->perguntasPropostaContrato as $pergunta)
                                <fieldset class="mb-4 border-bottom pb-3 orcamento-pergunta" data-categoria="{{ $pergunta->id_categoria_orcamento }}" hidden disabled>
                                    <label class="form-label fw-semibold">{{ $pergunta->titulo }}</label>
                                    @if($pergunta->tipo === 'texto')
                                        <textarea name="respostas[{{ $pergunta->id }}]" class="form-control" rows="3" required>{{ old('respostas.'.$pergunta->id) }}</textarea>
                                    @elseif($pergunta->tipo === 'opcoes')
                                        @foreach($pergunta->opcoesList() as $idx => $opcao)
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="respostas[{{ $pergunta->id }}]" id="prop{{ $pergunta->id }}_{{ $idx }}" value="{{ $idx }}" {{ $idx === 0 ? 'required' : '' }} @checked((string) old('respostas.'.$pergunta->id, '') === (string) $idx)>
                                                <label class="form-check-label" for="prop{{ $pergunta->id }}_{{ $idx }}">{{ $opcao }}</label>
                                            </div>
                                        @endforeach
                                    @elseif($pergunta->tipo === 'anexo')
                                        <input type="file" name="anexos[{{ $pergunta->id }}]" class="form-control" required>
                                    @endif
                                </fieldset>
                            @endforeach
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-outline-custom" id="enviarOrcamentoCategoria" disabled>Enviar proposta</button>
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Modal de Edição de Post (ÚNICA, FORA DO LOOP DE POSTS) --}}
<div class="modal fade" id="editPostModal" tabindex="-1" aria-labelledby="editPostModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form id="editPostForm" method="POST" action="" enctype="multipart/form-data">
                @csrf
                @method('PUT') {{-- Método HTTP para atualização --}}

                <div class="modal-header">
                    <h5 class="modal-title" id="editPostModalLabel">Editar Post</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="post_id" id="edit_post_id"> {{-- Campo oculto para o ID do post --}}

                    <div class="mb-3">
                        <label for="edit_nome" class="form-label">Título</label>
                        <input type="text" class="form-control" name="nome" id="edit_nome" required>
                    </div>

                    <div class="mb-4">
                        <label for="edit_descricao" class="form-label">Descrição</label>
                        <textarea class="form-control" name="descricao" id="edit_descricao" rows="3" required></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="edit_id_categoria_post_portfolio" class="form-label">Álbum (opcional)</label>
                        <select name="id_categoria_post_portfolio" id="edit_id_categoria_post_portfolio" class="form-select">
                            <option value="">Sem álbum</option>
                            @foreach(($categoriasPortfolio ?? collect()) as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->nome }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Seção para exibir imagens existentes e permitir remoção --}}
                    <div class="mb-3" id="existing_images_preview">
                        <label class="form-label">Imagens Atuais:</label>
                        <div class="d-flex flex-wrap gap-2" id="existing_images_container">
                            {{-- Imagens serão carregadas aqui via JS --}}
                        </div>
                    </div>

                    @include('usuarios.partials.post_image_upload', ['inputId' => 'edit_imagens', 'uploadLabel' => 'Adicionar novas imagens'])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-custom" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary-custom">Salvar Alterações</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal de Confirmação de Exclusão de Post (ÚNICA) --}}
<div class="modal fade" id="deletePostConfirmModal" tabindex="-1" aria-labelledby="deletePostConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deletePostConfirmModalLabel">Confirmar Exclusão</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <p>Tem certeza que deseja apagar este post?</p>
                <form id="deletePostForm" method="POST" action="">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="post_id_to_delete" id="post_id_to_delete">
                    <button type="submit" class="btn btn-danger me-2">Apagar</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                </form>
            </div>
        </div>
    </div>
</div>

    @include('usuarios.partials.portfolio_editor')

    @auth
        @if(Auth::user()->tipo_usuario == 2 && Auth::id() === $usuario->id && $portfolio)

            <div class="modal fade" id="novaCategoriaPortfolioModal" tabindex="-1" aria-labelledby="novaCategoriaPortfolioModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <form action="{{ route('categorias-posts-portfolio.store') }}" method="POST">
                            @csrf
                            <div class="modal-header">
                                <h5 class="modal-title" id="novaCategoriaPortfolioModalLabel">Novo álbum</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Nome</label>
                                    <input type="text" name="nome" class="form-control" required maxlength="255">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Ordem</label>
                                    <input type="number" name="ordem" class="form-control" value="0">
                                </div>
                                <div class="mb-0">
                                    <label class="form-label">Descrição (opcional)</label>
                                    <textarea name="descricao" class="form-control" rows="3"></textarea>
                                </div>
                                @include('usuarios.partials.categoria_icon_picker', ['pickerId' => 'nova_categoria_icone'])
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-outline-custom">Adicionar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="editarCategoriaPortfolioModal" tabindex="-1" aria-labelledby="editarCategoriaPortfolioModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <form id="editarCategoriaPortfolioForm" action="" method="POST">
                            @csrf
                            @method('PUT')
                            <div class="modal-header">
                                <h5 class="modal-title" id="editarCategoriaPortfolioModalLabel">Editar álbum</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label for="editar_categoria_portfolio_nome" class="form-label">Nome</label>
                                    <input type="text" name="nome" id="editar_categoria_portfolio_nome" class="form-control" required maxlength="255">
                                </div>
                                <div class="mb-3">
                                    <label for="editar_categoria_portfolio_ordem" class="form-label">Ordem</label>
                                    <input type="number" name="ordem" id="editar_categoria_portfolio_ordem" class="form-control" value="0">
                                </div>
                                <div class="mb-0">
                                    <label for="editar_categoria_portfolio_descricao" class="form-label">Descrição</label>
                                    <textarea name="descricao" id="editar_categoria_portfolio_descricao" class="form-control" rows="3"></textarea>
                                </div>
                                @include('usuarios.partials.categoria_icon_picker', ['pickerId' => 'editar_categoria_icone'])
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-outline-custom">Salvar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    @endauth

    {{-- A modal #postModal fica no navbar; a categoria acompanha a navegação do portfólio. --}}

</main>

@include('Components.footer')


<script>
    // Função para abrir a modal de edição de post
    function openEditPostModal(button) {
        const postId = button.dataset.postId;
        const postNome = button.dataset.postNome;
        const postDescricao = button.dataset.postDescricao;

        // Preenche os campos do formulário na modal de edição
        document.getElementById('edit_post_id').value = postId;
        document.getElementById('edit_nome').value = postNome;
        document.getElementById('edit_descricao').value = postDescricao;
        const catSel = document.getElementById('edit_id_categoria_post_portfolio');
        if (catSel) {
            catSel.value = button.dataset.postCategoria || '';
        }

        // Define a action do formulário para a rota de update
        const editForm = document.getElementById('editPostForm');
        editForm.querySelector('[data-post-image-upload]')?.dispatchEvent(new Event('post-images-reset'));
        editForm.action = `/posts/${postId}`; 

       
        const existingImagesContainer = document.getElementById('existing_images_container');
        existingImagesContainer.innerHTML = '';


        const postImagensJson = button.dataset.postImagens;
        if (postImagensJson) {
            const postImagens = JSON.parse(postImagensJson);
            postImagens.forEach(img => {
                const imgDiv = document.createElement('div');
                imgDiv.className = 'd-flex align-items-center me-2 mb-2';
                imgDiv.innerHTML = `
                    <img src="${img.caminho}" class="img-thumbnail me-2" style="width: 80px; height: 80px; object-fit: cover;" alt="Imagem do Post">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="imagens_para_remover[]" value="${img.id}" id="remove_img_${img.id}">
                        <label class="form-check-label" for="remove_img_${img.id}">Remover</label>
                    </div>
                `;
                existingImagesContainer.appendChild(imgDiv);
            });
        }

        // Abre a modal de edição
        const editModal = new bootstrap.Modal(document.getElementById('editPostModal'));
        editModal.show();
    }

    // Função para abrir a modal de confirmação de exclusão
    function openDeletePostConfirmModal(button) {
        const postId = button.dataset.postId;
        document.getElementById('post_id_to_delete').value = postId; // Define o ID no input oculto
        document.getElementById('deletePostForm').action = `/posts/${postId}`; // Define a action do formulário

        const deleteConfirmModal = new bootstrap.Modal(document.getElementById('deletePostConfirmModal'));
        deleteConfirmModal.show();
    }

    function openNovaCategoriaPortfolioModal() {
        const modalEl = document.getElementById('novaCategoriaPortfolioModal');
        if (!modalEl) return;

        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }

    function openEditCategoriaPortfolioModal(button) {
        const form = document.getElementById('editarCategoriaPortfolioForm');
        const nome = document.getElementById('editar_categoria_portfolio_nome');
        const ordem = document.getElementById('editar_categoria_portfolio_ordem');
        const descricao = document.getElementById('editar_categoria_portfolio_descricao');

        if (!form || !nome || !ordem || !descricao) return;

        form.action = button.dataset.updateUrl || '';
        nome.value = button.dataset.categoriaNome || '';
        ordem.value = button.dataset.categoriaOrdem || 0;
        descricao.value = button.dataset.categoriaDescricao || '';
        form.querySelectorAll('input[name="icone"]').forEach(input => {
            input.checked = input.value === (button.dataset.categoriaIcone || '');
        });
        form.querySelector('[data-icon-picker]')?.dispatchEvent(new Event('icon-picker-sync'));

        bootstrap.Modal.getOrCreateInstance(document.getElementById('editarCategoriaPortfolioModal')).show();
    }

    ['novaCategoriaPortfolioModal', 'editarCategoriaPortfolioModal'].forEach(function (modalId) {
        const modalEl = document.getElementById(modalId);
        if (!modalEl) return;

        modalEl.addEventListener('hidden.bs.modal', function () {
            if (document.querySelector('#editModalportfolio.show')) {
                document.body.classList.add('modal-open');
            }
        });
    });

    (function () {
        var btnNao = document.getElementById('btnConviteCadastroNao');
        if (!btnNao) return;
        btnNao.addEventListener('click', function () {
            var conviteEl = document.getElementById('modalConviteCadastroSolicitante');
            var propostaEl = document.getElementById('modalPropostaContrato');
            if (!conviteEl || !propostaEl) return;
            var conviteModal = bootstrap.Modal.getOrCreateInstance(conviteEl);
            conviteEl.addEventListener('hidden.bs.modal', function onHidden() {
                conviteEl.removeEventListener('hidden.bs.modal', onHidden);
                bootstrap.Modal.getOrCreateInstance(propostaEl).show();
            });
            conviteModal.hide();
        });
    })();
</script>


<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

<script src="{{ asset('js/orcamento-categorias.js') }}"></script>
<script src="{{ asset('js/portfolio-navigation.js') }}"></script>
<script src="{{ asset('js/portfolio-category-icons.js') }}"></script>
<script src="{{ asset('js/portfolio-settings.js') }}"></script>
<script src="{{ asset('js/portfolio-areas.js') }}"></script>
</body>
</html>
