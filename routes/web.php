<?php


use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\{
    CategoriaArtisticaController,
    UsuarioController,
    TipoUsuarioController,
    PortfolioArtistaController,
    ProfileController,
    PostPortfolioController,
    CategoriaPostPortfolioController
};
use App\Http\Controllers\NotificacaoController;
use App\Http\Controllers\FeedbackArtistaController;
use App\Http\Controllers\FeedbackContratanteController;
use App\Http\Controllers\PropostaContratoController;
use App\Http\Controllers\PerguntaPropostaContratoController;
use Illuminate\Http\Request;


//rotas admin : 
Route::get('/login_interno', [\App\Http\Controllers\LoginInternoController::class, 'create'])->name('loginInterno.form');
Route::post('/login_interno', [\App\Http\Controllers\LoginInternoController::class, 'store'])
    ->middleware('throttle:8,1')->name('loginInterno');

Route::get('/meu-perfil', [UsuarioController::class, 'editInterno'])->name('usuarios.editInterno');

Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/admin/configuracoes-visuais', [\App\Http\Controllers\ConfiguracaoSistemaController::class, 'edit'])->name('admin.configuracoes.edit');
    Route::put('/admin/configuracoes-visuais', [\App\Http\Controllers\ConfiguracaoSistemaController::class, 'update'])->name('admin.configuracoes.update');
    Route::resource('categorias-artisticas', CategoriaArtisticaController::class)
        ->parameters(['categorias-artisticas' => 'categoriaArtistica']);
});



// rotas site PUBLICO

Route::middleware('throttle:20,1')->group(function () {
    Route::get('/endereco/localizar', [\App\Http\Controllers\EnderecoController::class, 'buscar'])->name('endereco.localizar');
    Route::get('/endereco/reverso', [\App\Http\Controllers\EnderecoController::class, 'reverso'])->name('endereco.reverso');
});


Route::get('/home', function () {
    return view('home');
})->name('homepage');

Route::get('/sobre', function () {
    return view('sobre');
})->name('sobrepage');



Route::get('/', function () {
    return view('home');
});


Route::get('/solicitantes', [UsuarioController::class, 'listarContratantes'])->name('usuarios.contratantes');



// ->middleware(['auth', 'verified'])->name('dashboard');


Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});



Route::resource('usuarios', UsuarioController::class);

Route::get('/notificacoes', [NotificacaoController::class, 'index'])->middleware('auth');

Route::get('/usuarios/{id}/perfil-publico', [UsuarioController::class, 'showPerfilPublico'])->name('usuarios.perfilPublico');

Route::get('/cadastro', [UsuarioController::class, 'createCadastro'])->name('usuarios.cadastro');
Route::post('/cadastro', [UsuarioController::class, 'storeCadastro'])->name('usuarios.cadastrar');

Route::get('/cadastro/artista', [UsuarioController::class, 'createArtista'])->name('usuarios.createArtista');
Route::post('/cadastro/artista', [UsuarioController::class, 'storeArtista'])->name('usuarios.storeArtista');

Route::get('/cadastro/contratante', [UsuarioController::class, 'createContratante'])->name('usuarios.createContratante');
Route::post('/cadastro/contratante', [UsuarioController::class, 'storeContratante'])->name('usuarios.storeContratante');

Route::post('/usuarios/artista', [UsuarioController::class, 'storeArtista'])->name('usuarios.storeArtista');
Route::post('/usuarios/contratante', [UsuarioController::class, 'storeContratante'])->name('usuarios.storeContratante');



// portfolio 
Route::middleware(['auth'])->group(function () {
    Route::get('/areas-atuacao/buscar', [\App\Http\Controllers\AreaAtuacaoController::class, 'buscar'])
        ->middleware('throttle:120,1')->name('areas-atuacao.buscar');
    Route::post('/areas-atuacao', [\App\Http\Controllers\AreaAtuacaoController::class, 'store'])
        ->middleware('throttle:20,1')->name('areas-atuacao.store');
    Route::resource('portfolio', PortfolioArtistaController::class)->except(['show']);
});


Route::middleware(['auth'])->group(function () {
    Route::get('/perfil', [UsuarioController::class, 'perfil'])->name('perfil');
    Route::put('/perfil/senha', [UsuarioController::class, 'updatePassword'])->name('usuarios.password.update');
});

Route::middleware(['auth'])->group(function () {
    Route::post('/posts', [PostPortfolioController::class, 'store'])->name('posts.store');
    Route::put('/posts/{post}', [PostPortfolioController::class, 'update'])->name('posts.update'); 
    Route::delete('/posts/{post}', [PostPortfolioController::class, 'destroy'])->name('posts.destroy'); 

    Route::post('/categorias-posts-portfolio', [CategoriaPostPortfolioController::class, 'store'])->name('categorias-posts-portfolio.store');
    Route::put('/categorias-posts-portfolio/{categoriaPostPortfolio}', [CategoriaPostPortfolioController::class, 'update'])->name('categorias-posts-portfolio.update');
    Route::delete('/categorias-posts-portfolio/{categoriaPostPortfolio}', [CategoriaPostPortfolioController::class, 'destroy'])->name('categorias-posts-portfolio.destroy');
});

//proposta de trampo (visitante ou solicitante logado)
Route::post('/propostas', [PropostaContratoController::class, 'store'])->name('propostas.store');

Route::middleware(['auth'])->group(function () {
    Route::get('/perguntas-proposta', [PerguntaPropostaContratoController::class, 'index'])->name('perguntas-proposta.index');
    Route::post('/categorias-orcamento', [\App\Http\Controllers\CategoriaOrcamentoController::class, 'store'])->name('categorias-orcamento.store');
    Route::put('/categorias-orcamento/{categoriaOrcamento}', [\App\Http\Controllers\CategoriaOrcamentoController::class, 'update'])->name('categorias-orcamento.update');
    Route::delete('/categorias-orcamento/{categoriaOrcamento}', [\App\Http\Controllers\CategoriaOrcamentoController::class, 'destroy'])->name('categorias-orcamento.destroy');
    Route::post('/perguntas-proposta', [PerguntaPropostaContratoController::class, 'store'])->name('perguntas-proposta.store');
    Route::put('/perguntas-proposta/{perguntaPropostaContrato}', [PerguntaPropostaContratoController::class, 'update'])->name('perguntas-proposta.update');
    Route::delete('/perguntas-proposta/{perguntaPropostaContrato}', [PerguntaPropostaContratoController::class, 'destroy'])->name('perguntas-proposta.destroy');
});
    
Route::middleware('auth')->post('/responder-proposta/{id}', [PropostaContratoController::class, 'responder'])
     ->name('proposta.responder');

Route::middleware('auth')->post('/propostas/{proposta}/timeline', [PropostaContratoController::class, 'storeTimeline'])
    ->name('propostas.timeline.store');
Route::middleware('auth')->post('/propostas/{proposta}/finalizar', [PropostaContratoController::class, 'finalizar'])
    ->name('propostas.finalizar');

Route::middleware('auth')->get('/minhas-propostas', [PropostaContratoController::class, 'minhasPropostas'])->name('propostas.minhas');


     
//listagem dos usuários
Route::get('/artistas', [UsuarioController::class, 'listarPublico'])->name('usuarios.publico');
Route::get('/artistas/mapa', [UsuarioController::class, 'artistasMapa'])->name('usuarios.mapa');

Route::get('/perfil/{id}', [UsuarioController::class, 'showPublic'])->name('usuarios.public');
Route::post('/notificacoes/ler-todas', [NotificacaoController::class, 'lerTodas'])->name('notificacoes.lerTodas');
Route::post('/notificacoes/{id}/marcar-lida', [NotificacaoController::class, 'marcarComoLida']);



//FEEDBACKS

Route::middleware(['auth'])->group(function () {
    // Verificações separadas
    Route::get('/feedbacks/pendentes/artistas', [FeedbackArtistaController::class, 'verificarPendentes'])->name('feedbacks.pendentes.artistas');
    Route::get('/feedbacks/pendentes/contratantes', [FeedbackContratanteController::class, 'verificarPendentes'])->name('feedbacks.pendentes.contratantes');

    // Stores
    Route::post('/feedbacks/artistas', [FeedbackArtistaController::class, 'store'])->name('feedbacks.artistas.store');
    Route::post('/feedbacks/contratantes', [FeedbackContratanteController::class, 'store'])->name('feedbacks.contratantes.store');
});
//LOGOUT
Route::post('/logout', function () {
    Auth::logout();
    return redirect('/');
})->name('logout');

require __DIR__.'/auth.php';
