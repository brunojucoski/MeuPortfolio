@if(($usuario->compartilhar_localizacao || Auth::id() === $usuario->id) && ($usuario->cep || $usuario->bairro || $usuario->endereco))
    <p class="mb-2"><strong>Endereço:</strong> {{ preg_replace('/(\d{5})(\d{3})/', '$1-$2', $usuario->cep ?? '') }} , {{ $usuario->bairro }} , {{ $usuario->endereco }}</p>
@endif
