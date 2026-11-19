@extends('auth.form-layout')

@section('auth-title', 'Redefinir senha')
@section('auth-description', 'Escolha uma nova senha para acessar sua conta com segurança.')

@section('auth-form')
    <form method="POST" action="{{ route('password.store') }}">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div class="auth-form-fields">
            <div class="auth-field">
                <label for="email" class="form-label">E-mail</label>
                <input id="email" class="form-control @error('email') is-invalid @enderror" type="email" name="email"
                    value="{{ old('email', $request->email) }}" placeholder="seu@email.com" required autofocus autocomplete="username" inputmode="email"
                    @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                @error('email')<p id="email-error" class="field-hint-invalid">{{ $message }}</p>@enderror
            </div>
            <div class="auth-field">
                <label for="password" class="form-label">Nova senha</label>
                <input id="password" class="form-control @error('password') is-invalid @enderror" type="password" name="password"
                    required autocomplete="new-password"
                    @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                @error('password')<p id="password-error" class="field-hint-invalid">{{ $message }}</p>@enderror
            </div>
            <div class="auth-field">
                <label for="password_confirmation" class="form-label">Confirmar nova senha</label>
                <input id="password_confirmation" class="form-control @error('password_confirmation') is-invalid @enderror"
                    type="password" name="password_confirmation" required autocomplete="new-password"
                    @error('password_confirmation') aria-invalid="true" aria-describedby="password-confirmation-error" @enderror>
                @error('password_confirmation')<p id="password-confirmation-error" class="field-hint-invalid">{{ $message }}</p>@enderror
            </div>
        </div>
        <button type="submit" class="submit-btn">Salvar nova senha</button>
    </form>
    <p class="login-link text-center"><a href="{{ route('password.request') }}">Solicitar outro link</a></p>
@endsection
