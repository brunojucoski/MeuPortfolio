@extends('auth.form-layout')

@section('auth-title', 'Confirmar senha')
@section('auth-description', 'Para proteger sua conta, confirme sua senha antes de continuar.')

@section('auth-form')
    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf

        <div class="auth-form-fields">
            <div class="auth-field">
                <label for="password" class="form-label">Senha atual</label>
                <input id="password" class="form-control @error('password') is-invalid @enderror" type="password" name="password"
                    required autofocus autocomplete="current-password"
                    @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                @error('password')<p id="password-error" class="field-hint-invalid">{{ $message }}</p>@enderror
            </div>
        </div>
        <button type="submit" class="submit-btn">Confirmar e continuar</button>
    </form>
    <p class="login-link text-center"><a href="{{ route('perfil') }}">Voltar ao meu perfil</a></p>
@endsection
