<button type="{{ ($googleForm ?? false) ? 'submit' : 'button' }}" class="google-auth-button"
        @if($googleForm ?? false) form="{{ $googleForm }}" @else disabled title="Acesso com Google ainda não configurado" @endif>
    <img src="{{ asset('imgs/google-g.png') }}" width="20" height="20" alt="" aria-hidden="true">
    <span>{{ $googleLabel ?? 'Entrar com Google' }}</span>
</button>
