<nav class="navbar navbar-expand-lg fixed-top site-navbar" aria-label="Navegação principal">
    <div class="container-fluid">
        <a class="navbar-brand site-navbar-brand me-auto" href="{{ route('homepage') }}">@include('Components.brand')</a>
        <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasNavbar" aria-labelledby="offcanvasNavbarLabel">
            <div class="offcanvas-header">
                <a class="offcanvas-title site-navbar-brand" id="offcanvasNavbarLabel" href="{{ route('homepage') }}">@include('Components.brand')</a>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fechar"></button>
            </div>
            <div class="offcanvas-body">
                <ul class="navbar-nav flex-grow-1 pe-lg-3">
                    <li class="nav-item"><a class="nav-link mx-lg-2" href="{{ route('usuarios.publico') }}">Buscar portfólios</a></li>
                    <li class="nav-item"><a class="nav-link mx-lg-2" href="{{ route('sobrepage') }}">Sobre</a></li>
                </ul>
                @guest
                    <div class="navbar-guest-actions d-flex gap-2 mt-3 mt-lg-0 align-items-center">
                        <a class="btn btn-primary-custom" href="{{ route('usuarios.cadastro') }}">Cadastrar-se</a>
                        <a class="btn btn-primary-custom" href="{{ route('login') }}">Entrar</a>
                    </div>
                @endguest
            </div>
        </div>
        @auth
            <div class="navbar-account-actions">
                <div class="dropdown navbar-notifications">
                    <button type="button" class="navbar-notification-button position-relative" id="notificacoesDropdown" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notificações" title="Notificações" onclick="carregarNotificacoes()">
                        <i class="bi bi-bell" aria-hidden="true"></i>
                        @php
                            $naoLidas = \App\Models\Notificacao::where('usuario_id', Auth::id())->where('lida', false)->count();
                        @endphp
                        <span class="notification-count badge rounded-pill bg-secondary" id="contadorNotificacoes" @if($naoLidas === 0) hidden @endif>{{ $naoLidas }}</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificacoesDropdown" id="listaNotificacoes"></ul>
                </div>
                @include('Components.account-menu')
            </div>
        @endauth
        <button class="navbar-toggler ms-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNavbar" aria-controls="offcanvasNavbar" aria-label="Abrir navegação"><span class="navbar-toggler-icon"></span></button>
    </div>
</nav>
