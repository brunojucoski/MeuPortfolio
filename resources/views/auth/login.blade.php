<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login — MeuPortfólio</title>
    <link href="{{ asset('css/cadastro.css') }}" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/auth-forms.css') }}" rel="stylesheet">
</head>
<body class="auth-page auth-login-page">

@include('Components.auth-pixel-background')
@include('Components.navbarbootstrap')

<main class="cadastro-container">
    <div class="left-illustration">
        <img src="{{ $visualSistema->imagemUrl('login') }}" alt="Ilustração">
    </div>

    <div class="form-section" id="formulario-login">
        <h1 class="form-title h2">Login</h1>

        @if (session('status'))
            <div class="login-validation-banner" style="color: #0f5132; border-color: #badbcc; background: #d1e7dd;">
                {{ session('status') }}
            </div>
        @endif

        @if (session('success'))
            <div class="login-validation-banner" style="color: #0f5132; border-color: #badbcc; background: #d1e7dd;">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="login-validation-banner" role="alert">
                @foreach ($errors->all() as $erro)
                    <p class="mb-1 mb-md-0">{{ $erro }}</p>
                @endforeach
            </div>
        @endif

        <form action="{{ route('login') }}" method="POST" novalidate>
            @csrf

            <div class="auth-form-fields">
                <div class="auth-field">
                    <label for="email" class="form-label mb-1">E-mail</label>
                    <input
                        type="email"
                        name="email"
                        id="email"
                        class="form-control @error('email') is-invalid @enderror"
                        placeholder="seu@email.com"
                        value="{{ old('email') }}"
                        required
                        autocomplete="username"
                    >
                </div>

                <div class="auth-field">
                    <label for="password" class="form-label mb-1">Senha</label>
                    <input
                        type="password"
                        name="password"
                        id="password"
                        class="form-control @error('password') is-invalid @enderror"
                        placeholder="Senha"
                        required
                        autocomplete="current-password"
                    >
                </div>
            </div>

            <button type="submit" class="submit-btn">Entrar</button>
        </form>

        <hr class="my-4 opacity-25">

        <div class="auth-signup-actions">
            <a href="{{ route('usuarios.cadastro', ['tipo' => 'artista']) }}" class="cadastro-button">Cadastro artista</a>
            <a href="{{ route('usuarios.cadastro', ['tipo' => 'solicitante']) }}" class="cadastro-button">Cadastro solicitante</a>
        </div>
    </div>
</main>

@include('Components.footer')

</body>
</html>
