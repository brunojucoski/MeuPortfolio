<dialog open class="confirmacao-email form-section" id="confirmacao-email" aria-labelledby="confirmacao-titulo">
    <h2 class="form-title" id="confirmacao-titulo">Confirme seu e-mail</h2>
    <p>Use o código de seis dígitos recebido em <strong>{{ $emailConfirmacao }}</strong>. Ele vale por {{ \App\Notifications\CodigoCadastro::VALIDADE_MINUTOS }} minutos.</p>
    @if(session('envio'))<p role="status">{{ session('envio') }}</p>@endif
    @error('envio')<p role="alert" class="text-danger">{{ $message }}</p>@enderror
    <form method="post" action="{{ route('cadastro.confirmar') }}">
        @csrf
        <label class="form-label" for="codigo-confirmacao">Código de confirmação</label>
        <input class="form-control" id="codigo-confirmacao" name="codigo" type="text" autocomplete="one-time-code"
               inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autofocus @error('codigo') aria-invalid="true" aria-describedby="erro-codigo" @enderror>
        @error('codigo')<p id="erro-codigo" role="alert" class="text-danger">{{ $message }}</p>@enderror
        <button class="submit-btn w-100" type="submit">Confirmar e entrar</button>
    </form>
    <form method="post" action="{{ route('cadastro.reenviar') }}">
        @csrf
        <button class="btn btn-link" type="submit" id="reenviar-codigo" data-espera="{{ $espera }}">Reenviar código</button>
    </form>
    <a href="{{ route('usuarios.cadastro', ['tipo' => $perfilCadastro, 'orcamento_artista' => $artistaOrigemCadastro]) }}">Voltar ao cadastro</a>
</dialog>
<script src="{{ asset('js/confirmacao-email.js') }}" defer></script>
