<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('auth-title') | MeuPortfólio</title>
    <link href="{{ asset('css/cadastro.css') }}" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/auth-forms.css') }}" rel="stylesheet">
</head>
<body class="auth-page auth-login-page">
    @include('Components.auth-pixel-background')
    @include('Components.navbarbootstrap')

    <main class="cadastro-container" aria-labelledby="auth-form-title">
        @if($visualSistema->imagemUrl('login'))
            <div class="left-illustration">
                <img src="{{ $visualSistema->imagemUrl('login') }}" alt="" aria-hidden="true">
            </div>
        @endif

        <section class="form-section">
            <h1 class="form-title h2" id="auth-form-title">@yield('auth-title')</h1>
            @hasSection('auth-description')
                <p class="mb-4">@yield('auth-description')</p>
            @endif

            @if(session('status'))
                <div class="alert alert-success mb-4" role="status">
                    @if(session('status') === 'verification-link-sent')
                        Enviamos um novo link de confirmação para o seu e-mail.
                    @else
                        {{ session('status') }}
                    @endif
                </div>
            @endif
            @include('Components.form-validation-summary')

            @yield('auth-form')
        </section>
    </main>

    @include('Components.footer')
</body>
</html>
