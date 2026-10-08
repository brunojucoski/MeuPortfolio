<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Usuario;
use App\Models\TipoUsuario;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\CategoriaArtistica;
use App\Models\FeedbackArtista;
use App\Models\FeedbackContratante;
use Illuminate\Database\Eloquent\Builder;


class UsuarioController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $usuarios = \App\Models\Usuario::with('tipo')->get();
        return view('usuarios.index', compact('usuarios'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $tipos = TipoUsuario::all();
        return view('usuarios.create', compact('tipos'));
    }

    public function createCadastro(Request $request)
    {
        $perfilCadastro = old('perfil_cadastro', $request->query('tipo', 'artista'));
        if (! in_array($perfilCadastro, ['artista', 'solicitante'], true)) {
            $perfilCadastro = 'artista';
        }
        $artistaOrigemCadastro = $this->artistaOrigemCadastro(
            $request->old('orcamento_artista', $request->query('orcamento_artista'))
        );
        return view('usuarios.cadastro', compact('perfilCadastro', 'artistaOrigemCadastro'));
    }

    public function createArtista(Request $request) {
        $request->session()->reflash();
        return redirect()->route('usuarios.cadastro', ['tipo' => 'artista', 'orcamento_artista' => $request->query('orcamento_artista')]);
    }

    public function createContratante(Request $request) {
        $request->session()->reflash();
        return redirect()->route('usuarios.cadastro', ['tipo' => 'solicitante', 'orcamento_artista' => $request->query('orcamento_artista')]);
    }

    public function storeCadastro(Request $request)
    {
        $data = $request->validate([
            'perfil_cadastro' => 'required|string|in:artista,solicitante',
        ], [
            'perfil_cadastro.required' => 'Selecione o perfil de cadastro.',
            'perfil_cadastro.in' => 'Selecione artista ou solicitante.',
        ]);
        return $this->storeWithTipo($request, $data['perfil_cadastro'] === 'artista' ? 2 : 3);
    }
    

    public function storeArtista(Request $request)
    {
        return $this->storeWithTipo($request, 2); // 2 = artista
    }
    
    public function storeContratante(Request $request)
    {
        return $this->storeWithTipo($request, 3); // 3 = contratante
    }
    
    private function storeWithTipo(Request $request, $tipoUsuario)
    {
        $request->validate([
            'nome' => 'required|string|max:200',
            'documento' => 'required|string|unique:usuarios,documento',
            'email' => 'required|email|unique:usuarios,email',
            'senha' => 'required|string|min:6|confirmed',
            'telefone' => 'nullable|string|max:18',
            'data_nasc' => 'required|date|before:today',
            'cidade' => 'nullable|string|max:255',
            'cep' => 'nullable|string|max:20',
            'bairro' => 'nullable|string|max:255',
            'endereco' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ], [
            'nome.required' => 'Informe seu nome.',
            'documento.required' => 'Informe o CPF ou CNPJ.',
            'documento.unique' => 'Este documento já está cadastrado.',
            'email.required' => 'Informe seu e-mail.',
            'email.email' => 'Digite um e-mail válido.',
            'email.unique' => 'Este e-mail já está em uso.',
            'senha.required' => 'Defina uma senha.',
            'senha.min' => 'A senha deve ter pelo menos 6 caracteres.',
            'senha.confirmed' => 'A confirmação de senha não confere.',
            'data_nasc.required' => 'Informe sua data de nascimento.',
            'data_nasc.date' => 'Data de nascimento inválida.',
            'data_nasc.before' => 'A data de nascimento deve ser anterior a hoje.',
        ]);
        $artistaOrigemCadastro = $this->artistaOrigemCadastro($request->input('orcamento_artista'));
        $usuario = new Usuario();
        $usuario->fill($request->except(['senha', 'senha_confirmation', 'orcamento_artista']));
        $usuario->senha = Hash::make($request->senha);
        $usuario->tipo_usuario = $tipoUsuario;
        $usuario->save();

        $usuario->createToken('token')->plainTextToken;

        Auth::login($usuario);
        $request->session()->regenerate();

        $destino = $artistaOrigemCadastro
            ? redirect()->route('usuarios.perfilPublico', ['id' => $artistaOrigemCadastro])
            : redirect()->route('perfil');
        return $destino->with('success', 'Conta criada com sucesso! Você já está conectado.');
    }

    private function artistaOrigemCadastro($valor): ?int
    {
        if (!is_string($valor) && !is_int($valor)) return null;
        $id = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) return null;
        return Usuario::whereKey($id)->where('tipo_usuario', 2)->exists() ? $id : null;
    }
    



    public function perfil(Request $request)
    {
        $usuario = Auth::user();
        $usuario->load([
            'portfolioArtista.posts.imagens',
            'portfolioArtista.posts.categoriaPostPortfolio',
            'portfolioArtista.categoriasPostsPortfolio.coverPost.imagens',
            'portfolioArtista.perguntasPropostaContrato',
            'portfolioArtista.categoriasOrcamento' => fn ($query) => $query->withCount('perguntas'),
            'categoriasArtisticas',
            'todosFeedbacksRecebidosArtista.avaliador',
            'todosFeedbacksRecebidosContratante.avaliador',
        ]);

        $categoriaId = $this->parseCategoriaQuery($request);
        $feedbacks = $this->feedbackCollectionsForUsuario($usuario);
        $portfolioData = $this->resolvePortfolioListagem($usuario, $categoriaId);
        if ($categoriaId && $portfolioData['portfolio'] && ! $portfolioData['categoriaAtiva']) {
            abort(404);
        }

        $categorias = $this->categoriasDoEditor($usuario);
        $categoriasSelecionadas = $usuario->categoriasArtisticas->pluck('id')->toArray();

        return view('usuarios.perfil_publico', array_merge(
            $feedbacks,
            $portfolioData,
            compact('usuario', 'categorias', 'categoriasSelecionadas')
        ));
    }



    public function showPerfilPublico(Request $request, $id)
    {
        $usuario = Usuario::with([
            'portfolioArtista.posts.imagens',
            'portfolioArtista.posts.categoriaPostPortfolio',
            'portfolioArtista.categoriasPostsPortfolio.coverPost.imagens',
            'portfolioArtista.perguntasPropostaContrato',
            'portfolioArtista.categoriasOrcamento' => fn ($query) => $query->withCount('perguntas'),
            'categoriasArtisticas',
            'todosFeedbacksRecebidosArtista.avaliador',
            'todosFeedbacksRecebidosContratante.avaliador',
        ])->findOrFail($id);

        $categoriaId = $this->parseCategoriaQuery($request);
        $feedbacks = $this->feedbackCollectionsForUsuario($usuario);
        $portfolioData = $this->resolvePortfolioListagem($usuario, $categoriaId);
        if ($categoriaId && $portfolioData['portfolio'] && ! $portfolioData['categoriaAtiva']) {
            abort(404);
        }

        $categorias = $this->categoriasDoEditor($usuario);
        $categoriasSelecionadas = $usuario->categoriasArtisticas->pluck('id')->toArray();

        return view('usuarios.perfil_publico', array_merge(
            $feedbacks,
            $portfolioData,
            compact('usuario', 'categorias', 'categoriasSelecionadas')
        ));
    }

    private function parseCategoriaQuery(Request $request): ?int
    {
        $q = $request->query('categoria');
        if ($q === null || $q === '') {
            return null;
        }

        return (int) $q;
    }

    private function categoriasDoEditor(Usuario $usuario)
    {
        if (!session()->hasOldInput('categorias_form')) return $usuario->categoriasArtisticas;
        $ids = old('categorias', []);
        if (!is_array($ids)) return collect();
        $ids = array_slice(array_filter($ids, fn ($id) => (is_int($id) || is_string($id)) && ctype_digit((string) $id)), 0, 100);
        return CategoriaArtistica::whereIn('id', $ids)->get();
    }

    /**
     * @return array{feedbacksParaMedia: \Illuminate\Support\Collection, feedbacksParaLista: \Illuminate\Support\Collection}
     */
    private function feedbackCollectionsForUsuario(Usuario $usuario): array
    {
        $feedbacksParaMedia = collect();
        $feedbacksParaLista = collect();

        if ($usuario->tipo_usuario == 2) {
            $feedbacksParaMedia = $usuario->todosFeedbacksRecebidosArtista;
            $feedbacksParaLista = $usuario->todosFeedbacksRecebidosArtista->sortByDesc('created_at')->take(3);
        } elseif ($usuario->tipo_usuario == 3) {
            $feedbacksParaMedia = $usuario->todosFeedbacksRecebidosContratante;
            $feedbacksParaLista = $usuario->todosFeedbacksRecebidosContratante->sortByDesc('created_at')->take(3);
        }

        return compact('feedbacksParaMedia', 'feedbacksParaLista');
    }

    /**
     * @return array{portfolio: ?\App\Models\PortfolioArtista, categoriasPortfolio: \Illuminate\Support\Collection, categoriaAtiva: ?\App\Models\CategoriaPostPortfolio, posts: \Illuminate\Support\Collection}
     */
    private function resolvePortfolioListagem(Usuario $usuario, ?int $categoriaId): array
    {
        $portfolio = $usuario->portfolioArtista;
        $categoriasPortfolio = $portfolio
            ? $portfolio->categoriasPostsPortfolio
            : collect();

        $categoriaAtiva = null;
        if ($categoriaId && $portfolio) {
            $categoriaAtiva = $categoriasPortfolio->firstWhere('id', $categoriaId);
        }

        $allPosts = $portfolio && $portfolio->relationLoaded('posts')
            ? $portfolio->posts
            : ($portfolio ? $portfolio->posts : collect());

        if ($categoriaAtiva) {
            $posts = $allPosts->where('id_categoria_post_portfolio', $categoriaAtiva->id)->values();
        } else {
            $posts = $allPosts->filter(fn ($p) => $p->id_categoria_post_portfolio === null)->values();
        }

        return compact('portfolio', 'categoriasPortfolio', 'categoriaAtiva', 'posts');
    }





    public function showshowPublic($id)
    {
        $usuario = Usuario::with('portfolioArtista.posts.imagens', 'categoriasArtisticas')->findOrFail($id);
        $posts = $usuario->portfolioArtista->posts ?? collect();
    
        $categorias = CategoriaArtistica::all();
        $categoriasSelecionadas = $usuario->categoriasArtisticas->pluck('id')->toArray();

    
        return view('usuarios.perfil_publico', compact('usuario', 'posts', 'categorias', 'categoriasSelecionadas'));
    }





    public function editInterno()
{
    $usuario = Auth::user();
    $tiposUsuario = TipoUsuario::all();

    return view('usuarios.edit_interno', compact('usuario', 'tiposUsuario'));
}







    public function edit($id)
{
    $usuario = Usuario::findOrFail($id);
    $tiposUsuario = TipoUsuario::all();

    return view('usuarios.edit_interno', compact('usuario', 'tiposUsuario'));
}
    /**
     * Update the specified resource in storage.
     */






    public function update(Request $request, $id)
{
    $usuario = Usuario::findOrFail($id);

    $request->validate([
        'nome' => 'required|string|max:255',
        'telefone' => 'nullable|string|max:20',
        'cidade' => 'nullable|string|max:255',
        'cep' => 'nullable|string|max:20',
        'bairro' => 'nullable|string|max:255',
        'endereco' => 'nullable|string|max:255',
        'latitude' => 'nullable|numeric|between:-90,90',
        'longitude' => 'nullable|numeric|between:-180,180',
        'senha' => 'nullable|string|min:8|confirmed',
        // Sem regra "image": GIF (principalmente animado) falha em alguns ambientes com "image";
        // mime/extension são conferidos por mimes + max em KB.
        'foto_perfil' => 'nullable|file|mimes:jpeg,jpg,png,gif|max:8192',
    ]);

    $usuario->nome = $request->nome;
    $usuario->telefone = $request->telefone;
    $usuario->cidade = $request->cidade;
    $usuario->cep = $request->cep;
    $usuario->bairro = $request->bairro;
    $usuario->endereco = $request->endereco;
    $usuario->latitude = $request->latitude;
    $usuario->longitude = $request->longitude;

    if ($request->filled('senha')) {
        $usuario->senha = Hash::make($request->senha);
    }

    if ($request->hasFile('foto_perfil')) {
    
        if ($usuario->foto_perfil) {
            Storage::disk('public')->delete($usuario->foto_perfil);
        }

        $path = $request->file('foto_perfil')->store('fotos_perfil', 'public');
        $usuario->foto_perfil = $path;
    }

    $usuario->save();

    return redirect()->back()->with('success', 'Perfil atualizado com sucesso!');
}

    public function updatePassword(Request $request)
    {
        $request->validate([
            'senha' => 'required|string|min:8|confirmed',
        ], [
            'senha.required' => 'Informe a nova senha.',
            'senha.min' => 'A senha deve ter no mínimo 8 caracteres.',
            'senha.confirmed' => 'A confirmação não confere com a nova senha.',
        ]);

        $usuario = Auth::user();
        $usuario->senha = Hash::make($request->senha);
        $usuario->save();

        return redirect()->back()->with('success', 'Senha alterada com sucesso!');
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

//listar artistas no site 
    
public function listarPublico(Request $request)
{
    $query = $this->artistasPublicosQuery($request);

    $usuarios = (clone $query)->with(['categoriasArtisticas', 'portfolioArtista.feedbacksRecebidos'])
        ->latest()->paginate(5)->withQueryString();
    if ($request->ajax()) {
    return view('partials.lista_usuarios', compact('usuarios'))->render();
}
    $primeiraLocalizacao = (clone $query)
        ->whereBetween('latitude', [-90, 90])
        ->whereBetween('longitude', [-180, 180])
        ->orderBy('id')->first(['latitude', 'longitude']);
    $centroMapa = $primeiraLocalizacao
        ? [(float) $primeiraLocalizacao->latitude, (float) $primeiraLocalizacao->longitude]
        : [-14.235, -51.9253];
    $categorias = CategoriaArtistica::all();
  

    $cidades = Usuario::whereNotNull('cidade')
    ->where('tipo_usuario', 2)
    ->distinct()
    ->pluck('cidade');

    return view('artistas', compact('usuarios', 'categorias', 'cidades', 'centroMapa'));
}

    private function artistasPublicosQuery(Request $request): Builder
    {
        return Usuario::where('tipo_usuario', 2)->whereNotNull('nome')
            ->when($request->filled('categoria'), function (Builder $query) use ($request) {
                $query->whereHas('categoriasArtisticas', function (Builder $categorias) use ($request) {
                    $categorias->where('categorias_usuarios.id_categoria', $request->categoria);
                });
            })
            ->when($request->filled('cidade'), function (Builder $query) use ($request) {
                $query->where('cidade', 'like', '%'.$request->cidade.'%');
            });
    }

    public function artistasMapa(Request $request)
    {
        $dados = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'categoria' => 'nullable|integer|min:1',
            'cidade' => 'nullable|string|max:255',
        ]);
        $latitude = (float) $dados['latitude'];
        $longitude = (float) $dados['longitude'];
        $raioKm = 15;
        $limite = 200;
        $angulo = $raioKm / 6371.0088;
        $deltaLatitude = rad2deg($angulo);
        $latMin = max(-90, $latitude - $deltaLatitude);
        $latMax = min(90, $latitude + $deltaLatitude);

        $query = $this->artistasPublicosQuery($request)
            ->whereBetween('latitude', [$latMin, $latMax])
            ->whereBetween('longitude', [-180, 180]);

        // The bounding box uses the coordinate index before the exact spherical radius check.
        if ($latMin > -90 && $latMax < 90) {
            $deltaLongitude = rad2deg(asin(min(1, sin($angulo) / cos(deg2rad($latitude)))));
            $lonMin = $longitude - $deltaLongitude;
            $lonMax = $longitude + $deltaLongitude;
            $query->where(function (Builder $bounds) use ($lonMin, $lonMax) {
                if ($lonMin < -180) {
                    $bounds->where('longitude', '>=', $lonMin + 360)->orWhere('longitude', '<=', $lonMax);
                } elseif ($lonMax > 180) {
                    $bounds->where('longitude', '>=', $lonMin)->orWhere('longitude', '<=', $lonMax - 360);
                } else {
                    $bounds->whereBetween('longitude', [$lonMin, $lonMax]);
                }
            });
        }

        // Haversine avoids precision loss for artists very close to the search center.
        $distancia = '(POWER(SIN(RADIANS(latitude - ?) / 2), 2) + COS(RADIANS(?)) * COS(RADIANS(latitude)) * POWER(SIN(RADIANS(longitude - ?) / 2), 2))';
        $bindings = [$latitude, $latitude, $longitude];
        $artistas = $query->whereRaw($distancia.' <= (? * 1.0)', [...$bindings, pow(sin($angulo / 2), 2)])
            ->orderByRaw($distancia.' ASC', $bindings)->orderBy('id')
            ->with([
                'categoriasArtisticas',
                'portfolioArtista' => fn ($portfolio) => $portfolio
                    ->withAvg('feedbacksRecebidos', 'nota')->withCount('feedbacksRecebidos'),
            ])
            ->limit($limite + 1)
            ->get(['id', 'nome', 'cidade', 'bairro', 'latitude', 'longitude', 'foto_perfil']);

        $payload = $artistas->take($limite)->map(function (Usuario $artista) {
            $portfolio = $artista->portfolioArtista;
            $media = $portfolio?->feedbacks_recebidos_avg_nota;

            return [
                'id' => $artista->id,
                'nome' => $artista->nome,
                'nome_artistico' => $portfolio?->nome_artistico,
                'cidade' => $artista->cidade,
                'bairro' => $artista->bairro,
                'latitude' => (float) $artista->latitude,
                'longitude' => (float) $artista->longitude,
                'foto' => $artista->foto_perfil ? asset('storage/'.$artista->foto_perfil) : asset('imgs/user.png'),
                'categorias' => $artista->categoriasArtisticas->pluck('nome')->values(),
                'perfil_url' => route('usuarios.perfilPublico', $artista->id),
                'avaliacao_media' => $media ? number_format($media, 1, ',', '.') : null,
                'avaliacao_total' => $portfolio?->feedbacks_recebidos_count ?? 0,
            ];
        })->values();

        return response()->json([
            'artistas' => $payload,
            'raio_km' => $raioKm,
            'limite' => $limite,
            'tem_mais' => $artistas->count() > $limite,
        ]);
    }

    
//listar contratantes no site 

public function listarContratantes(Request $request)
{
    $usuarios = Usuario::where('tipo_usuario', 3)
        ->when($request->cidade, function ($query) use ($request) {
            $query->where('cidade', $request->cidade);
        })
        ->orderBy('created_at', 'desc')
        ->paginate(5);

    if ($request->ajax()) {
        return response()->json([
            'html' => view('partials.lista_contratantes', compact('usuarios'))->render(),
            'next_page_url' => $usuarios->nextPageUrl()
        ]);
    }

    $cidades = Usuario::where('tipo_usuario', 3)
        ->whereNotNull('cidade')
        ->pluck('cidade')
        ->unique()
        ->sort()
        ->values();

    return view('contratantes', compact('usuarios', 'cidades'));
}
    //editar e add foto de perfil 
    
    
}
