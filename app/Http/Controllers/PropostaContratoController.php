<?php

namespace App\Http\Controllers;

use App\Models\Notificacao;
use App\Models\PortfolioArtista;
use App\Models\PropostaContrato;
use App\Models\RespostaPropostaPergunta;
use App\Models\TimelinePropostaContrato;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class PropostaContratoController extends Controller
{
    public function index()
    {
        $propostas = PropostaContrato::with(['artista', 'usuarioAvaliador'])->get();

        return response()->json($propostas);
    }

    public function store(Request $request)
    {
        $guest = ! Auth::check();

        if (! $guest && (int) Auth::user()->tipo_usuario !== 3) {
            abort(403, 'Apenas solicitantes podem enviar propostas.');
        }

        $request->validate([
            'id_artista' => 'required|exists:portfolio_artistas,id',
        ], [
            'id_artista.required' => 'Artista não selecionado.',
            'id_artista.exists' => 'O artista informado não existe.',
        ]);

        $portfolio = PortfolioArtista::findOrFail($request->id_artista);

        $categorias = $portfolio->categoriasOrcamento()->whereHas('perguntas')->get();
        if ($categorias->count() === 1 && ! $request->filled('id_categoria_orcamento')) {
            $request->merge(['id_categoria_orcamento' => $categorias->first()->id]);
        }
        $validatedCategoria = $request->validate([
            'id_categoria_orcamento' => ['required', 'integer', Rule::in($categorias->pluck('id')->all())],
        ], ['id_categoria_orcamento' => 'Selecione uma categoria de orçamento deste artista.']);
        $categoria = $categorias->firstWhere('id', (int) $validatedCategoria['id_categoria_orcamento']);
        $perguntas = $categoria->perguntas()->where('id_portfolio_artista', $portfolio->id)->get();

        if ($perguntas->isEmpty()) {
            return redirect()->back()->with('error', 'Este artista ainda não configurou o formulário de proposta.');
        }

        $rules = [];
        foreach ($perguntas as $p) {
            if ($p->tipo === 'texto') {
                $rules['respostas.'.$p->id] = 'required|string|max:10000';
            } elseif ($p->tipo === 'opcoes') {
                $rules['respostas.'.$p->id] = 'required|integer|min:0';
            } elseif ($p->tipo === 'anexo') {
                $rules['anexos.'.$p->id] = 'required|file|max:10240|mimes:jpeg,jpg,png,gif,pdf,doc,docx,zip,txt';
            }
        }
        $request->validate($rules);

        foreach ($perguntas as $p) {
            if ($p->tipo === 'opcoes') {
                $idx = (int) $request->input('respostas.'.$p->id);
                $opts = $p->opcoesList();
                if ($idx < 0 || $idx >= count($opts)) {
                    return redirect()->back()->with('error', 'Opção inválida para a pergunta: '.$p->titulo);
                }
            }
        }

        $idAvaliador = Auth::id();
        if ($guest) {
            $idAvaliador = Usuario::idVisitanteNaoIdentificado();
            if (! $idAvaliador) {
                return redirect()->back()->with('error', 'Não foi possível registrar o orçamento: execute as migrações do sistema (usuário interno ausente).');
            }
        }

        $proposta = PropostaContrato::create([
            'id_artista' => $portfolio->id,
            'id_categoria_orcamento' => $categoria->id,
            'categoria_orcamento_nome' => $categoria->nome,
            'id_usuario_avaliador' => $idAvaliador,
            'status' => 'Aguardando resposta',
        ]);

        foreach ($perguntas as $p) {
            $row = [
                'id_proposta' => $proposta->id,
                'id_pergunta' => $p->id,
                'texto_resposta' => null,
                'indice_opcao' => null,
                'caminho_anexo' => null,
            ];
            if ($p->tipo === 'texto') {
                $row['texto_resposta'] = $request->input('respostas.'.$p->id);
            } elseif ($p->tipo === 'opcoes') {
                $row['indice_opcao'] = (int) $request->input('respostas.'.$p->id);
            } elseif ($p->tipo === 'anexo') {
                $file = $request->file('anexos.'.$p->id);
                $row['caminho_anexo'] = $file->store('propostas_anexos', 'public');
            }
            RespostaPropostaPergunta::create($row);
        }

        $portfolio->load('usuario');
        $nomeContratante = Auth::check()
            ? (Auth::user()->nome ?? 'Solicitante')
            : (Usuario::find($idAvaliador)?->nome ?? 'Não se identificou');

        if ($portfolio->usuario) {
            Notificacao::create([
                'usuario_id' => $portfolio->usuario->id,
                'remetente_id' => $idAvaliador,
                'mensagem' => "Você recebeu uma nova proposta de trabalho de {$nomeContratante}.",
                'lida' => false,
                'proposta_id' => $proposta->id,
            ]);
        }

        if (! $guest) {
            TimelinePropostaContrato::create([
                'id_proposta' => $proposta->id,
                'id_usuario' => Auth::id(),
                'tipo' => TimelinePropostaContrato::TIPO_EVENTO,
                'codigo_evento' => TimelinePropostaContrato::EVT_ENVIO,
                'texto' => null,
            ]);
        }

        $proposta->load(['respostasPergunta.pergunta']);
        $telefoneProfissional = $portfolio->usuario?->telefone;
        $mensagemWa = $this->montarMensagemWhatsappProposta($proposta);
        $urlWhatsapp = $this->montarUrlWhatsapp($telefoneProfissional, $mensagemWa);

        if ($guest) {
            if ($urlWhatsapp) {
                return redirect()->away($urlWhatsapp);
            }

            return redirect()->back()->with('success', 'Proposta enviada com sucesso! O profissional não possui telefone cadastrado; não foi possível abrir o WhatsApp.');
        }

        if ($urlWhatsapp) {
            return redirect()->back()
                ->with('success', 'Proposta enviada com sucesso!')
                ->with('prompt_whatsapp_proposta', true)
                ->with('whatsapp_proposta_url', $urlWhatsapp);
        }

        return redirect()->back()->with('success', 'Proposta enviada com sucesso! O profissional não possui telefone cadastrado; não foi possível sugerir envio pelo WhatsApp.');
    }

    public function show($id)
    {
        $proposta = PropostaContrato::with(['artista', 'usuarioAvaliador', 'respostasPergunta.pergunta'])->findOrFail($id);

        return response()->json($proposta);
    }

    public function update(Request $request, $id)
    {
        $proposta = PropostaContrato::findOrFail($id);

        $validated = $request->validate([
            'status' => 'sometimes|string|max:50',
            'motivo' => 'sometimes|string|max:5000',
        ]);

        $proposta->update($validated);

        return response()->json($proposta);
    }

    public function destroy($id)
    {
        $proposta = PropostaContrato::findOrFail($id);
        $proposta->delete();

        return response()->json(['message' => 'Proposta excluída com sucesso.']);
    }

    public function notificacoes()
    {
        $usuario = Auth::user();

        if ($usuario->tipo_usuario != 2) {
            return response()->json([]);
        }

        $portfolio = $usuario->portfolioArtista;

        if (! $portfolio) {
            return response()->json([]);
        }

        $propostas = PropostaContrato::where('id_artista', $portfolio->id)
            ->with('usuarioAvaliador')
            ->latest()
            ->get();

        return response()->json($propostas);
    }

    public function responder(Request $request, $id)
    {
        try {
            $proposta = PropostaContrato::findOrFail($id);
            $usuario = Auth::user();

            if ($usuario->tipo_usuario != 2) {
                return redirect()->back()->with('error', 'Apenas artistas podem responder propostas.');
            }

            $portfolio = $usuario->portfolioArtista;
            if (! $portfolio) {
                return redirect()->back()->with('error', 'Você precisa ter um portfólio para responder propostas.');
            }

            if ($proposta->id_artista !== $portfolio->id) {
                return redirect()->back()->with('error', 'Esta proposta não pertence a você.');
            }

            if ($proposta->ehPropostaSemIdentificacao()) {
                return redirect()->back()->with('error', 'Este orçamento foi enviado sem cadastro na plataforma. Combine aceite ou recusa diretamente com a pessoa pelo WhatsApp.');
            }

            $resposta = $request->input('status');
            $motivo = $request->input('motivo');

            if (! in_array($resposta, ['aceita', 'recusada'])) {
                return redirect()->back()->with('error', 'Resposta inválida.');
            }

            if (empty(trim($motivo))) {
                return redirect()->back()->with('error', 'O motivo não pode ficar em branco.');
            }

            if ($resposta === 'recusada') {
                $proposta->status = 'Recusada';
            } else {
                $proposta->status = 'Aguardando execução';
            }

            $proposta->motivo = $motivo;
            $proposta->save();

            TimelinePropostaContrato::create([
                'id_proposta' => $proposta->id,
                'id_usuario' => $usuario->id,
                'tipo' => TimelinePropostaContrato::TIPO_EVENTO,
                'codigo_evento' => $resposta === 'recusada'
                    ? TimelinePropostaContrato::EVT_RECUSA
                    : TimelinePropostaContrato::EVT_ACEITE,
                'texto' => $motivo,
            ]);

            $telefone = $usuario->telefone ? "Telefone para contato: {$usuario->telefone}. " : '';
            $nomeExibicao = $portfolio->nome_artistico ?: $usuario->nome;
            $mensagem = $resposta === 'recusada'
                ? "Sua proposta enviada a {$nomeExibicao} foi recusada pelo artista {$usuario->nome}. Motivo: \"{$motivo}\"."
                : "Sua proposta foi aprovada por {$usuario->nome}. {$telefone}Observações: \"{$motivo}\".";

            if ($proposta->id_usuario_avaliador) {
                Notificacao::create([
                    'usuario_id' => $proposta->id_usuario_avaliador,
                    'remetente_id' => $usuario->id,
                    'mensagem' => $mensagem,
                    'proposta_id' => $proposta->id,
                    'lida' => false,
                ]);
            }

            return redirect()->back()->with('success', 'Resposta registrada com sucesso.');

        } catch (\Exception $e) {
            \Log::error('Erro ao processar resposta da proposta: '.$e->getMessage());
            \Log::error($e->getTraceAsString());

            return redirect()->back()->with('error', 'Erro ao processar sua resposta.');
        }
    }

    public function lerTodas()
    {
        Notificacao::where('user_id', auth()->id())->update(['lida' => true]);

        return response()->json(['success' => true]);
    }

    public function minhasPropostas(Request $request)
    {
        $usuario = Auth::user();
        $status = $request->input('status');
        $avaliadorId = $request->input('avaliador_id');

        $propostas = collect();

        if ($usuario->tipo_usuario == 2) {
            $portfolio = $usuario->portfolioArtista;
            if ($portfolio) {
                $query = PropostaContrato::where('id_artista', $portfolio->id)
                    ->with(['usuarioAvaliador', 'artista.usuario', 'respostasPergunta.pergunta', 'timeline.usuario']);

                if ($status) {
                    $query->where('status', $status);
                }

                if ($avaliadorId) {
                    $query->where('id_usuario_avaliador', $avaliadorId);
                }

                $propostas = $query->latest()->get();
            }
        } elseif ($usuario->tipo_usuario == 3) {
            $query = PropostaContrato::where('id_usuario_avaliador', $usuario->id)
                ->with(['artista.usuario', 'usuarioAvaliador', 'respostasPergunta.pergunta', 'timeline.usuario']);

            if ($status) {
                $query->where('status', $status);
            }

            $propostas = $query->latest()->get();
        }

        $avaliadores = PropostaContrato::query()
            ->whereNotNull('id_usuario_avaliador')
            ->select('id_usuario_avaliador')
            ->with('usuarioAvaliador')
            ->distinct()
            ->get()
            ->pluck('usuarioAvaliador')
            ->filter()
            ->unique('id');

        return view('propostas.minhas_propostas', compact('propostas', 'usuario', 'avaliadores'));
    }

    public function storeTimeline(Request $request, PropostaContrato $proposta)
    {
        if (! $this->usuarioPodeParticiparDaProposta($proposta)) {
            abort(403);
        }

        if ($proposta->ehPropostaSemIdentificacao()) {
            return redirect()->back()->with('error', 'Não há timeline para orçamentos enviados sem cadastro; use o WhatsApp para combinar com o solicitante.');
        }

        if ($proposta->status === 'Recusada') {
            return redirect()->back()->with('error', 'Não é possível enviar mensagens nesta proposta.');
        }

        $request->validate([
            'mensagem' => 'required|string|max:2000',
        ], [
            'mensagem.required' => 'Digite uma mensagem.',
        ]);

        TimelinePropostaContrato::create([
            'id_proposta' => $proposta->id,
            'id_usuario' => Auth::id(),
            'tipo' => TimelinePropostaContrato::TIPO_MENSAGEM,
            'codigo_evento' => null,
            'texto' => $request->input('mensagem'),
        ]);

        return redirect()->back()->with('success', 'Mensagem registrada na timeline.');
    }

    public function finalizar(Request $request, PropostaContrato $proposta)
    {
        if ((int) Auth::user()->tipo_usuario !== 2) {
            abort(403);
        }

        if (! $this->usuarioEhArtistaDaProposta($proposta)) {
            abort(403);
        }

        if ($proposta->ehPropostaSemIdentificacao()) {
            return redirect()->back()->with('error', 'Não é possível concluir pelo site orçamentos enviados sem cadastro.');
        }

        if ($proposta->status !== 'Aguardando execução') {
            return redirect()->back()->with('error', 'Só é possível concluir propostas em "Aguardando execução".');
        }

        $proposta->status = 'Finalizada';
        $proposta->save();

        TimelinePropostaContrato::create([
            'id_proposta' => $proposta->id,
            'id_usuario' => Auth::id(),
            'tipo' => TimelinePropostaContrato::TIPO_EVENTO,
            'codigo_evento' => TimelinePropostaContrato::EVT_FINALIZADA,
            'texto' => null,
        ]);

        if ($proposta->usuarioAvaliador) {
            Notificacao::create([
                'usuario_id' => $proposta->id_usuario_avaliador,
                'remetente_id' => Auth::id(),
                'mensagem' => 'O artista marcou sua proposta #'.$proposta->id.' como concluída. Você pode deixar seu feedback.',
                'lida' => false,
                'proposta_id' => $proposta->id,
            ]);
        }

        return redirect()->back()->with('success', 'Proposta marcada como concluída.');
    }

    private function montarMensagemWhatsappProposta(PropostaContrato $proposta): string
    {
        $proposta->loadMissing('respostasPergunta.pergunta');
        $linhas = [
            'Ola, acabo de lhe enviar uma proposta de orçamento via MeuPortfólio, aqui estão os dados que eu informei :',
        ];
        if ($proposta->categoria_orcamento_nome) {
            $linhas[] = 'Categoria: '.$proposta->categoria_orcamento_nome;
        }
        $sorted = $proposta->respostasPergunta->sortBy(function ($r) {
            $p = $r->pergunta;

            return ($p->ordem ?? 0) * 100000 + $p->id;
        });
        foreach ($sorted as $resp) {
            $pergunta = $resp->pergunta;
            if (! $pergunta) {
                continue;
            }
            if ($pergunta->tipo === 'texto') {
                $valor = trim((string) ($resp->texto_resposta ?? ''));
            } elseif ($pergunta->tipo === 'opcoes') {
                $opts = $pergunta->opcoesList();
                $idx = (int) ($resp->indice_opcao ?? -1);
                $valor = $opts[$idx] ?? '';
            } else {
                $valor = $resp->caminho_anexo
                    ? 'Arquivo anexado (disponível no MeuPortfólio).'
                    : '';
            }
            $linhas[] = '• '.$pergunta->titulo.': '.$valor;
        }

        return implode("\n", $linhas);
    }

    private function montarUrlWhatsapp(?string $telefone, string $mensagem): ?string
    {
        $digitos = preg_replace('/\D+/', '', (string) $telefone);
        if ($digitos === '') {
            return null;
        }
        if (strlen($digitos) >= 10 && strlen($digitos) <= 11 && ! str_starts_with($digitos, '55')) {
            $digitos = '55'.$digitos;
        }

        return 'https://wa.me/'.$digitos.'?text='.rawurlencode($mensagem);
    }

    private function usuarioPodeParticiparDaProposta(PropostaContrato $proposta): bool
    {
        if ($proposta->id_usuario_avaliador && (int) $proposta->id_usuario_avaliador === (int) Auth::id()) {
            return true;
        }

        $portfolio = Auth::user()->portfolioArtista;

        return $portfolio && (int) $portfolio->id === (int) $proposta->id_artista;
    }

    private function usuarioEhArtistaDaProposta(PropostaContrato $proposta): bool
    {
        $portfolio = Auth::user()->portfolioArtista;

        return $portfolio && (int) $portfolio->id === (int) $proposta->id_artista;
    }
}
