<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Cadastro — MeuPortfólio</title>
    <link href="{{ asset('css/cadastro.css') }}" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/auth-forms.css') }}" rel="stylesheet">
    <link href="{{ asset('css/lever-switch.css') }}" rel="stylesheet">
    <script src="{{ asset('js/vendor/imask.min.js') }}" defer></script>
    <script src="{{ asset('js/cadastro.js') }}" defer></script>
</head>
<body class="auth-page auth-register-page">

@include('Components.auth-pixel-background')
@include('Components.navbarbootstrap')

<main class="cadastro-container">
    <div class="left-illustration">
        <img id="cadastro-ilustracao" src="{{ $visualSistema->imagemUrl('cadastro_' . $perfilCadastro) }}"
             alt="{{ $perfilCadastro === 'artista' ? 'Ilustração Artista' : 'Ilustração Solicitante' }}">
    </div>

    <div class="form-section" id="formulario-login">
        <h1 class="form-title" id="cadastro-titulo" aria-live="polite">{{ $perfilCadastro === 'artista' ? 'Cadastro de artista' : 'Cadastro de solicitante' }}</h1>

        @include('Components.form-validation-summary')

        <form id="form-cadastro" action="{{ route('usuarios.cadastrar') }}" method="POST" data-address-form
              data-geocode-url="{{ route('endereco.localizar') }}" data-reverse-url="{{ route('endereco.reverso') }}">
            @csrf
            @if($artistaOrigemCadastro)
                <input type="hidden" name="orcamento_artista" value="{{ $artistaOrigemCadastro }}">
            @endif

            <div class="cadastro-profile-fallback" id="cadastro-profile-fallback">
                <label for="cadastro-tipo" class="form-label">Perfil de cadastro</label>
                <select id="cadastro-tipo" name="perfil_cadastro" class="form-select" required>
                    <option value="artista" @selected($perfilCadastro === 'artista')
                            data-title="Cadastro de artista" data-image="{{ $visualSistema->imagemUrl('cadastro_artista') }}"
                            data-image-alt="Ilustração Artista">Artista</option>
                    <option value="solicitante" @selected($perfilCadastro === 'solicitante')
                            data-title="Cadastro de solicitante" data-image="{{ $visualSistema->imagemUrl('cadastro_solicitante') }}"
                            data-image-alt="Ilustração Solicitante">Solicitante</option>
                </select>
            </div>

            <label class="cadastro-profile-toggle lever-switch {{ $perfilCadastro === 'solicitante' ? 'is-requester' : '' }}"
                   id="cadastro-profile-toggle" for="cadastro-profile-switch" hidden>
                <span class="cadastro-profile-label cadastro-profile-label-artist">Artista</span>
                <span class="toggle-container">
                    <input class="toggle-input" type="checkbox" role="switch" id="cadastro-profile-switch"
                           aria-label="Cadastrar como solicitante" aria-controls="cadastro-titulo cadastro-tipo cadastro-ilustracao"
                           @checked($perfilCadastro === 'solicitante')>
                    <span class="toggle-handle-wrapper" aria-hidden="true">
                        <span class="toggle-handle">
                            <span class="toggle-handle-knob"></span>
                            <span class="toggle-handle-bar-wrapper"><span class="toggle-handle-bar"></span></span>
                        </span>
                    </span>
                    <span class="toggle-base" aria-hidden="true"><span class="toggle-base-inside"></span></span>
                </span>
                <span class="cadastro-profile-label cadastro-profile-label-requester">Solicitante</span>
            </label>

            @include('usuarios.partials.cadastro-campos')

            <button type="submit" class="submit-btn">Criar conta</button>

            <p class="login-link">Já tem conta? <a href="{{ route('login') }}">Entrar</a></p>
        </form>
    </div>
</main>

@include('Components.footer')

</body>
</html>
