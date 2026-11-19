@extends('auth.form-layout')

@section('auth-title', 'Confirmar e-mail')
@section('auth-description', 'Confirme seu e-mail pelo link recebido. Se necessário, solicite um novo envio abaixo e confira também a pasta de spam.')

@section('auth-form')
    <form method="POST" action="{{ route('verification.send') }}">
        @csrf
        <button type="submit" class="submit-btn">Reenviar confirmação</button>
    </form>
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <p class="login-link text-center">
            <button type="submit" class="btn p-0 text-decoration-underline" style="color: var(--roxo-appolo);">Sair da conta</button>
        </p>
    </form>
@endsection
