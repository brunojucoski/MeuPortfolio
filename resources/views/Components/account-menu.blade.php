@php
    $accountUser = Auth::user();
    $accountPhoto = $accountUser->foto_perfil ? asset('storage/' . $accountUser->foto_perfil) : asset('imgs/user.png');
@endphp
<div class="account-menu" data-account-menu>
    <div class="account-menu-surface" id="accountMenuPanel">
        <div class="account-menu-content" data-account-content hidden inert>
            <div class="account-menu-header">
                <a href="{{ route('usuarios.perfilPublico', $accountUser->id) }}" class="account-menu-name">{{ $accountUser->nome }}</a>
                <span class="account-menu-email">{{ $accountUser->email }}</span>
            </div>
            <div class="account-menu-items" role="menu" aria-label="Minha conta">
                @if((int) $accountUser->tipo_usuario === 1)
                    <a class="account-menu-item" role="menuitem" href="{{ route('admin.configuracoes.edit') }}"><i class="bi bi-gear" aria-hidden="true"></i> Configurações visuais</a>
                @endif
                <button type="button" class="account-menu-item" role="menuitem" data-bs-toggle="offcanvas" data-bs-target="#editOffcanvas"><i class="bi bi-pencil-square" aria-hidden="true"></i> Editar perfil</button>
                @if((int) $accountUser->tipo_usuario === 2 && $accountUser->portfolioArtista)
                    <a class="account-menu-item" role="menuitem" href="{{ route('perguntas-proposta.index') }}"><i class="bi bi-ui-checks" aria-hidden="true"></i> Formulário de orçamento</a>
                @endif
                <a class="account-menu-item" role="menuitem" href="{{ route('propostas.minhas') }}"><i class="bi bi-file-earmark-text" aria-hidden="true"></i> Meus orçamentos</a>
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button class="account-menu-item" role="menuitem" type="submit"><i class="bi bi-box-arrow-right" aria-hidden="true"></i> Sair</button>
                </form>
            </div>
        </div>
    </div>
    <button type="button" class="account-avatar-button" id="dropdownProfile" data-account-toggle aria-expanded="false" aria-haspopup="menu" aria-controls="accountMenuPanel" aria-label="Abrir menu da conta" title="Minha conta">
        <img src="{{ $accountPhoto }}" alt="" class="account-avatar" width="44" height="44">
    </button>
</div>
