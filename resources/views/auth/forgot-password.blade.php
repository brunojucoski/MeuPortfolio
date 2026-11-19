@extends('auth.form-layout')

@section('auth-title', 'Recuperar senha')
@section('auth-description', 'Informe o e-mail da sua conta para receber um link de recuperação de senha.')

@section('auth-form')
    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <div class="auth-form-fields">
            <div class="auth-field">
                <label for="email" class="form-label">E-mail</label>
                <input id="email" class="form-control @error('email') is-invalid @enderror" type="email" name="email"
                    value="{{ old('email') }}" placeholder="seu@email.com" required autofocus autocomplete="email" inputmode="email"
                    @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                @error('email')<p id="email-error" class="field-hint-invalid">{{ $message }}</p>@enderror
            </div>
        </div>
        <button type="submit" class="submit-btn">Enviar link de recuperação</button>
    </form>
    <p class="login-link text-center"><a href="{{ route('login') }}">Voltar para entrar</a></p>
@endsection
