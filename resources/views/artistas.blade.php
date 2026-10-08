<!-- Página que lista todos os artistas cadastrados na plataforma de maneira dinâmica, podendo filtrar pela região e/ou categoria artistica cadastrada -->


<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Artistas</title>
    <link href="{{ asset('css/perfil.css') }}" rel="stylesheet">
    <link href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/artistas-map.css') }}" rel="stylesheet">
    <link href="{{ asset('css/lever-switch.css') }}" rel="stylesheet">
</head>
<body>

@include('Components.navbarbootstrap')












@php
    $visualizarMapa = request('visualizacao_artistas') === 'mapa';
@endphp

<main>


<div class="container-listagem d-flex"> 
        <link rel="stylesheet" href="{{ asset('css/usuarios_publicos.css') }}"> {{-- opcional --}}

    <div class="container mt-5">
            <form method="GET" action="{{ route('usuarios.publico') }}" class="row g-3 align-items-center mb-4" id="filtroForm">
                <input type="hidden" name="visualizacao_artistas" id="visualizacao-artistas" value="{{ $visualizarMapa ? 'mapa' : 'lista' }}">
            
                <div class="col-md-4">
                    <select name="categoria" class="form-control" onchange="document.getElementById('filtroForm').submit();">
                        <option value="">Selecione uma área de atuação</option>
                        @foreach($categorias as $categoria)
                            <option value="{{ $categoria->id }}" {{ request('categoria') == $categoria->id ? 'selected' : '' }}>
                                {{ $categoria->nome }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4">
                    <select name="cidade" class="form-control" onchange="document.getElementById('filtroForm').submit();">
                        <option value="">Todas as cidades</option>
                        @foreach($cidades as $cidade)
                            <option value="{{ $cidade }}" {{ request('cidade') == $cidade ? 'selected' : '' }}>
                                {{ $cidade }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4 d-flex justify-content-md-end">
                    <label class="artistas-view-toggle lever-switch {{ $visualizarMapa ? 'is-map' : '' }}" for="artistas-view-switch">
                        <span class="artistas-view-label artistas-view-label-list">Lista</span>
                        {{-- Switch by njesenberger, Uiverse.io (MIT). --}}
                        <span class="toggle-container">
                            <input class="toggle-input" type="checkbox" role="switch" id="artistas-view-switch"
                                aria-label="Visualizar artistas no mapa" aria-controls="artistas-map-view artistas-list-view" @checked($visualizarMapa)>
                            <span class="toggle-handle-wrapper" aria-hidden="true">
                                <span class="toggle-handle">
                                    <span class="toggle-handle-knob"></span>
                                    <span class="toggle-handle-bar-wrapper"><span class="toggle-handle-bar"></span></span>
                                </span>
                            </span>
                            <span class="toggle-base" aria-hidden="true"><span class="toggle-base-inside"></span></span>
                        </span>
                        <span class="artistas-view-label artistas-view-label-map">Mapa</span>
                    </label>
                </div>
            </form>

                    <section id="artistas-map-view" class="artistas-map-view {{ $visualizarMapa ? '' : 'd-none' }}" aria-label="Mapa de artistas">
                        <div class="artistas-map-heading">
                            <div>
                                <span class="artistas-map-kicker">Mapa de artistas</span>
                                <h2>Artistas em um raio de 15 km</h2>
                            </div>
                            <span class="artistas-map-count" data-map-count role="status" aria-live="polite">Carregando...</span>
                        </div>

                        <div class="artistas-map-container">
                            <div id="artistas-map" class="artistas-map" aria-label="Área de busca de artistas"></div>
                            <div class="artistas-map-empty d-none" data-map-empty-message role="status">
                            </div>
                        </div>
                        <div class="artistas-map-status">
                            <span>Raio de busca: <strong>15 km</strong></span>
                            <button type="button" class="artistas-map-reset" data-map-reset title="Voltar à área inicial" aria-label="Voltar à área inicial">
                                <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
                            </button>
                        </div>
                    </section>

                    <div id="artistas-list-view" class="{{ $visualizarMapa ? 'd-none' : '' }}">
                    <div id="lista-usuarios">
                        @include('partials.lista_usuarios', ['usuarios' => $usuarios])
                    </div>

                <div class="text-center mt-4 p-3">
                    @if ($usuarios->hasMorePages())
                        <button id="load-more" class="btn btn-outline-custom" data-next-page="{{ $usuarios->currentPage() + 1 }}">
                            +
                        </button>
                    @endif
                </div>
                    </div>
        
    </div>
</div> 

</main>

@include('Components.footer')


<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script type="application/json" id="artistas-map-config">@json(['centro' => $centroMapa, 'endpoint' => route('usuarios.mapa'), 'raioKm' => 15])</script>
<script src="{{ asset('js/artistas-map.js') }}"></script>
<script>
    $('#load-more').on('click', function () {
        var button = $(this);
        var nextPage = button.data('next-page');
        var url = "{{ route('usuarios.publico') }}" + '?page=' + nextPage;

        // Adiciona filtros ativos na URL (se houver)
        var form = $('#filtroForm');
        var categoria = form.find('select[name="categoria"]').val();
        var cidade = form.find('select[name="cidade"]').val();
        if (categoria) url += '&categoria=' + categoria;
        if (cidade) url += '&cidade=' + cidade;

        $.get(url, function (data) {
            $('#lista-usuarios').append(data);

            // Atualiza número da próxima página
            button.data('next-page', nextPage + 1);

            // Se não tiver mais páginas, remove o botão
            if (data.trim() === '') {
                button.remove();
            }
        });
    });
</script>



</body> 
