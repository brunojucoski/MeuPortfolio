@foreach ($usuarios as $usuario)
    <div class="card mb-4 p-3 shadow-sm">
        <div class="row align-items-center">
            <div class="col-auto imagem-centralizada-mobile p-3">
                <img src="{{ \App\Support\MediaStorage::publicUrl($usuario->foto_perfil) ?: asset('imgs/user.png') }}" class="rounded-circle" width="100" height="100">
            </div>
            <div class="col-md-8">
                <h5 class="text-nome">{{ $usuario->nome }}</h5>
                <p class="mb-1">
                    {{ \Carbon\Carbon::parse($usuario->data_nasc)->age }} anos<br>
                    {{ $usuario->cidade ?? 'Cidade não informada' }}
                </p>
                <div class="mt-1">
                    @php
                        $feedbacks = $usuario->portfolioArtista->feedbacksRecebidos ?? collect();
                        $media = $feedbacks->avg('nota');
                    @endphp

                    @if($media)
                        <strong>{{ number_format($media, 1) }}</strong> ⭐ ({{ $feedbacks->count() }} avaliação{{ $feedbacks->count() > 1 ? 's' : '' }})
                    @else
                        <em>Sem avaliações ainda</em>
                    @endif
                </div>
            </div>
            <div class="col-md-2 text-end">
                <a href="{{ route('usuarios.perfilPublico', $usuario->id) }}" class="btn btn-purple btn-outline-custom">Ver perfil</a>
            </div>
        </div>
    </div>
@endforeach
