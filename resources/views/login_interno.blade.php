<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login interno — MeuPortfólio</title>
    <link href="{{ asset('css/cadastro.css') }}" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/auth-forms.css') }}" rel="stylesheet">
    @include('Components.system-theme')
</head>
<body class="auth-page auth-login-page auth-internal-page">
<main class="cadastro-container">
    <div class="form-section">
        <h1 class="form-title">Login interno</h1>
        @include('Components.form-validation-summary')
        <form method="POST" action="{{ route('loginInterno') }}">
            @csrf
            <div class="auth-form-fields">
                <div class="auth-field">
                    <label class="form-label" for="email">E-mail</label>
                    <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required autocomplete="username" placeholder="seu@email.com">
                </div>
                <div class="auth-field">
                    <label class="form-label" for="password">Senha</label>
                    <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror" required autocomplete="current-password" placeholder="Senha">
                </div>
            </div>
            <button class="submit-btn" type="submit">Entrar</button>
        </form>
    </div>
</main>
</body>
</html>

