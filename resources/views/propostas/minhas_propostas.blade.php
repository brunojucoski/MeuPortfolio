@extends('Components.navbarbootstrap')
<!DOCTYPE html>

<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="{{ asset('css/perfil.css') }}" rel="stylesheet">
    <title>Minhas Propostas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        .card-proposta {
            border: 1px solid #ddd;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 0 10px rgba(0,0,0,0.03);
        }

        .btn-custom {
            background-color: #7a00ff;
            color: #fff;
            border-radius: 25px;
            padding: 6px 20px;
        }

        .btn-custom-outline {
            border: 2px solid #7a00ff;
            color: #7a00ff;
            border-radius: 25px;
            padding: 6px 20px;
        }

        .status-label {
            font-weight: bold;
            color: #7a00ff;
        }
    </style>
</head>
<body>



<main>





@php
    $portfolio = $usuario->portfolioArtista;
@endphp

 
@if ($errors->any())
      <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
      <div class="modal-dialog">
          <div class="modal-content">
              <div class="modal-header">
                  <h5 class="text-nome" id="successModalLabel"> MeuPortfólio </h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                     @foreach ($errors->all() as $erro)
                <li>{{ $erro }}</li>
            @endforeach
              </div>
              <div class="modal-footer">
                  <button type="button" class="btn btn-outline-custom" data-bs-dismiss="modal"> Fechar </button>
              </div>
          </div>
      </div>
  </div>
        <script>
      document.addEventListener("DOMContentLoaded", function() {
          var successModal = new bootstrap.Modal(document.getElementById('successModal'));
          successModal.show();
      });
  </script>
       
@endif
        













    <div class="container my-5">
        <h2 class="mb-4 botao_home" style="text-transform: uppercase;">Meus orçamentos</h2>


        
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    {{-- Filtros --}}
  <form id="formFiltrosPropostas" method="GET" action="{{ route('propostas.minhas') }}" class="row g-3 mb-4">
    <div class="col-md-4">
        <label for="status" class="form-label">Status do orçamento</label>
        <select name="status" id="status" class="form-select">
            <option value="">Todos</option>
            <option value="Aguardando resposta" {{ request('status') == 'Aguardando resposta' ? 'selected' : '' }}>Aguardando resposta</option>
            <option value="Recusada" {{ request('status') == 'Recusada' ? 'selected' : '' }}>Recusada</option>
            <option value="Aguardando execução" {{ request('status') == 'Aguardando execução' ? 'selected' : '' }}>Aguardando execução</option>
            <option value="Finalizada" {{ request('status') == 'Finalizada' ? 'selected' : '' }}>Finalizada</option>
        </select>
    </div>

    @if($usuario->tipo_usuario == 2)
    <div class="col-md-4">
        <label for="avaliador_id" class="form-label">Solicitante</label>
        <select name="avaliador_id" id="avaliador_id" class="form-select">
            <option value="">Todos</option>
            @foreach($avaliadores as $avaliador)
                <option value="{{ $avaliador->id }}" {{ request('avaliador_id') == $avaliador->id ? 'selected' : '' }}>
                    {{ $avaliador->nome }}
                </option>
            @endforeach
        </select>
    </div>
    @endif

    
</form>

<!-- 
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @elseif(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
 -->


        @if($propostas->isEmpty())
            <p class="text-muted">Nenhuma proposta encontrada.</p>
        @else
            @foreach($propostas as $proposta)
                @php $semCadastroVisitante = $proposta->ehPropostaSemIdentificacao(); @endphp
                <div class="card-proposta">
                    <h5>Proposta #{{ $proposta->id }}</h5>
                    @if($proposta->categoria_orcamento_nome)
                        <p class="mb-2"><strong>Categoria:</strong> {{ $proposta->categoria_orcamento_nome }}</p>
                    @endif
                    <p class="text-muted small mb-2">Enviada em {{ $proposta->created_at->format('d/m/Y H:i') }}</p>
                    <p><strong>Status:</strong> <span class="status-label">{{ $proposta->status }}</span></p>

                    @if($semCadastroVisitante)
                        <div class="alert alert-info small py-2 mb-2">
                            Orçamento enviado sem cadastro na plataforma. Combine aceite, valores e prazos diretamente com a pessoa pelo WhatsApp; resposta pela plataforma, timeline e conclusão pelo site não estão disponíveis neste caso.
                        </div>
                    @endif

                    @if($proposta->motivo)
                        <p><strong>Motivo / observações (resposta do artista):</strong> {{ $proposta->motivo }}</p>
                    @endif

                    @if($proposta->respostasPergunta->isNotEmpty())
                        <div class="mt-3">
                            <strong>Respostas do solicitante:</strong>
                            <ul class="list-unstyled small mt-2 mb-0">
                                @foreach($proposta->respostasPergunta as $resp)
                                    <li class="mb-2 border-start border-3 ps-2">
                                        <span class="text-muted">{{ $resp->pergunta?->titulo ?? 'Pergunta removida' }}</span>
                                        @if($resp->pergunta && $resp->pergunta->tipo === 'texto')
                                            <div>{{ $resp->texto_resposta }}</div>
                                        @elseif($resp->pergunta && $resp->pergunta->tipo === 'opcoes')
                                            @php $opts = $resp->pergunta->opcoesList(); @endphp
                                            <div>{{ isset($opts[$resp->indice_opcao]) ? $opts[$resp->indice_opcao] : '—' }}</div>
                                        @elseif($resp->pergunta && $resp->pergunta->tipo === 'anexo' && $resp->caminho_anexo)
                                            <div><a href="{{ asset('storage/' . $resp->caminho_anexo) }}" target="_blank" rel="noopener">Baixar anexo</a></div>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if($usuario->tipo_usuario == 2)
                        <p class="mt-2"><strong>Solicitante:</strong> {{ $proposta->usuarioAvaliador->nome ?? 'Não se identificou' }}</p>

                        @if($proposta->status === 'Aguardando resposta' && ! $semCadastroVisitante)
                            <button type="button" class="btn btn-outline-custom btn-sm" data-bs-toggle="modal" data-bs-target="#responderModal{{ $proposta->id }}">
                                Responder Proposta
                            </button>
                        @endif
                    @else
                        <p class="mt-2"><strong>Artista:</strong> {{ $proposta->artista->usuario->nome ?? 'Desconhecido' }}</p>
                    @endif

                    <div class="mt-3 d-flex flex-wrap gap-2 align-items-center">
                        @if(! $semCadastroVisitante)
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#timelineModal{{ $proposta->id }}">
                                <i class="bi bi-chat-left-text"></i> Ver timeline
                            </button>
                        @endif
                        @if($usuario->tipo_usuario == 2 && $proposta->status === 'Aguardando execução' && ! $semCadastroVisitante)
                            <form action="{{ route('propostas.finalizar', $proposta) }}" method="POST" class="d-inline" onsubmit="return confirm('Marcar esta proposta como concluída? O solicitante será notificado e poderá enviar feedback.');">
                                @csrf
                                <button type="submit" class="btn btn-outline-custom btn-sm">Marcar como concluída</button>
                            </form>
                        @endif
                    </div>
                </div>

                @if(! $semCadastroVisitante)
                <div class="modal fade" id="timelineModal{{ $proposta->id }}" tabindex="-1" aria-labelledby="timelineModalLabel{{ $proposta->id }}" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="timelineModalLabel{{ $proposta->id }}">Timeline — Proposta #{{ $proposta->id }}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-4" style="max-height: 320px; overflow-y: auto;">
                                    @forelse($proposta->timeline as $ev)
                                        <p class="small mb-2 border-bottom pb-2 text-start">{{ $ev->linhaDescritiva() }}</p>
                                    @empty
                                        <p class="text-muted small">Nenhum registro ainda.</p>
                                    @endforelse
                                </div>
                                @if($proposta->status !== 'Recusada')
                                    <hr>
                                    <form action="{{ route('propostas.timeline.store', $proposta) }}" method="POST">
                                        @csrf
                                        <label class="form-label small fw-semibold">Nova mensagem na timeline</label>
                                        <textarea name="mensagem" class="form-control mb-2" rows="3" required maxlength="2000" placeholder="Comunique-se com o artista ou o solicitante..."></textarea>
                                        <button type="submit" class="btn btn-outline-custom btn-sm">Enviar mensagem</button>
                                    </form>
                                @else
                                    <p class="text-muted small mb-0">Proposta recusada — apenas leitura.</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                @if($usuario->tipo_usuario == 2 && $proposta->status === 'Aguardando resposta' && ! $semCadastroVisitante)
                    <!-- Modal de Resposta -->
                    <div class="modal fade p-5 "  id="responderModal{{ $proposta->id }}" tabindex="-1" aria-labelledby="responderModalLabel{{ $proposta->id }}" aria-hidden="true">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="responderModalLabel{{ $proposta->id }}">Responder Proposta</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <form id="respostaForm{{ $proposta->id }}" action="{{ route('proposta.responder', $proposta->id) }}" method="POST">
                                        @csrf
                                        <div class="mb-3">
                                            <label for="motivo{{ $proposta->id }}" class="form-label"> Informe abaixo a justificativa caso recuse o trabalho ou informações relevantes sobre a execução do trabalho caso aceite a proposta . </label>
                                            <textarea class="form-control" id="motivo{{ $proposta->id }}" name="motivo" rows="3"></textarea>
                                        </div>
                                        <input type="hidden" name="status" id="status{{ $proposta->id }}">
                                        <div class="d-flex justify-content-end gap-2">
                                            <button type="button" class="btn btn-outline-danger" onclick="enviarResposta({{ $proposta->id }}, 'recusada')">Recusar</button>
                                            <button type="button" class="btn btn-custom" onclick="enviarResposta({{ $proposta->id }}, 'aceita')">Aceitar</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            @endforeach
        @endif
    </div>

    <!-- Scripts -->
     <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.7/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
           
        document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('formFiltrosPropostas');
        if (!form) return;
        form.querySelectorAll('select').forEach(select => {
            select.addEventListener('change', function () {
                form.submit();
            });
        });
    });
       
       
       
       function enviarResposta(propostaId, status) {
            const form = document.getElementById(`respostaForm${propostaId}`);
            const motivoInput = document.getElementById(`motivo${propostaId}`);
            const statusInput = document.getElementById(`status${propostaId}`);
            
            // Remove espaços em branco do início e fim do texto
            const motivo = motivoInput.value.trim();
            
            if (!motivo) {
                alert('Por favor, informe o motivo da sua resposta.');
                motivoInput.focus();
                return;
            }
            
            // Define o status e envia o formulário
            statusInput.value = status;
            form.submit();
        }

        // Foca no textarea quando a modal abrir
        document.addEventListener('DOMContentLoaded', function() {
            const modals = document.querySelectorAll('[id^="responderModal"]');
            modals.forEach(modal => {
                modal.addEventListener('shown.bs.modal', function() {
                    const propostaId = this.id.replace('responderModal', '');
                    const motivoInput = document.getElementById(`motivo${propostaId}`);
                    if (motivoInput) {
                        setTimeout(() => motivoInput.focus(), 100);
                    }
                });
            });
        });


        
    </script>
</main>
@include('Components.footer')

</body>




</html>
