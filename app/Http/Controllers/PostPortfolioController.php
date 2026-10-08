<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PostPortfolio;
use App\Models\PostImagem;
use App\Models\CategoriaPostPortfolio;
use App\Models\TipoUsuario;
use App\Models\PortfolioArtista;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;



class PostPortfolioController extends Controller



{


    public function __construct()
{
    $this->middleware('auth')->only(['store', 'create', 'edit', 'update', 'destroy']);
}




    public function store(Request $request)
    {
        $user = Auth::user();


        if (!Auth::check()) {
             dd('Usuário não está autenticado.');
         }
        // Verifica se é artista
         if ($user->tipo_usuario != 2) {
             abort(403, 'Acesso não autorizado.');
         }

        
         $portfolio = $user->portfolioArtista;
         if (!$portfolio) {
             return back()->with('error', 'Você precisa ter um portfólio antes de postar.');
         }

        if ($request->input('id_categoria_post_portfolio') === '' || $request->input('id_categoria_post_portfolio') === null) {
            $request->merge(['id_categoria_post_portfolio' => null]);
        }

        $request->validate([
            'nome' => 'required|string|max:255',
            'descricao' => 'required|string|max:1000',
            'imagens' => 'nullable|array|max:20',
            'imagens.*' => 'file|mimes:jpeg,jpg,png,gif|max:8192',
            'id_categoria_post_portfolio' => 'nullable|integer|exists:categorias_posts_portfolio,id',
        ]);

        $idCategoria = $request->input('id_categoria_post_portfolio');
        if ($idCategoria) {
            $categoria = CategoriaPostPortfolio::find((int) $idCategoria);
            if (! $categoria || (int) $categoria->id_portfolio_artista !== (int) $portfolio->id) {
                return back()->with('error', 'Álbum inválido para este portfólio.');
            }
        }

        // Cria o post
    
        $post = PostPortfolio::create([
            'nome' => $request->nome,
            'descricao' => $request->descricao,
            'id_portfolio' => $portfolio->id,
            'id_categoria_post_portfolio' => $idCategoria ? (int) $idCategoria : null,
        ]);
        if ($request->hasFile('imagens')) { 
            foreach ($request->file('imagens') as $imagem) {
                $caminho = $imagem->store('posts', 'public');
    
                PostImagem::create([
                    'post_id' => $post->id,
                    'caminho_imagem' => $caminho,
                ]);
            }
        }
    
        return redirect()->back()->with('success', 'Post criado com sucesso!');
    }


    public function create()
{
    $user = auth()->user();
    if ($user->tipo_usuario_id != 2) {
        abort(403, 'Apenas artistas podem criar posts.');
    }
    return view('posts.create');
}

   public function edit($id)
    {
        $post = PostPortfolio::with('imagens')->findOrFail($id); // Carrega as imagens do post

        // Verifica se o usuário logado é o dono do portfólio ao qual o post pertence
        if (Auth::id() !== $post->portfolio->id_usuario) {
            abort(403, 'Você não tem permissão para editar este post.');
        }

        // Esta view 'posts.edit' é para um formulário de edição completo,
        // mas no seu caso, usaremos uma modal na perfil_publico.blade.php.
        // Este método 'edit' pode não ser estritamente necessário se a modal for preenchida via JS.
        // No entanto, é bom tê-lo para uma rota de edição caso precise de uma página dedicada.
        return view('posts.edit', compact('post'));
    }

    /**
     * Atualiza um post de portfólio existente.
     */
    public function update(Request $request, $id)
    {
        $post = PostPortfolio::findOrFail($id);

        // Autorização: Apenas o dono do portfólio pode editar o post
        if (Auth::id() !== $post->portfolio->id_usuario) {
            abort(403, 'Você não tem permissão para atualizar este post.');
        }

        if ($request->input('id_categoria_post_portfolio') === '' || $request->input('id_categoria_post_portfolio') === null) {
            $request->merge(['id_categoria_post_portfolio' => null]);
        }

        $request->validate([
            'nome' => 'required|string|max:255',
            'descricao' => 'required|string|max:1000',
            'imagens' => 'nullable|array|max:20',
            'imagens.*' => 'nullable|file|mimes:jpeg,jpg,png,gif|max:8192', // Permite novas imagens, GIF incluído
            'imagens_para_remover' => 'nullable|array', // Array de IDs de imagens a serem removidas
            'imagens_para_remover.*' => 'exists:posts_imgs,id', // Valida se os IDs existem na tabela
            'id_categoria_post_portfolio' => 'nullable|integer|exists:categorias_posts_portfolio,id',
        ]);

        $portfolio = $post->portfolio;
        $idCategoria = $request->input('id_categoria_post_portfolio');
        if ($idCategoria) {
            $categoria = CategoriaPostPortfolio::find((int) $idCategoria);
            if (! $categoria || (int) $categoria->id_portfolio_artista !== (int) $portfolio->id) {
                return back()->with('error', 'Álbum inválido para este portfólio.');
            }
        }

        // Atualiza os campos de texto do post
        $post->update([
            'nome' => $request->nome,
            'descricao' => $request->descricao,
            'id_categoria_post_portfolio' => $idCategoria ? (int) $idCategoria : null,
        ]);

        // 1. Remover imagens existentes (se IDs foram enviados para remoção)
        if ($request->has('imagens_para_remover')) {
            $imagensParaRemover = $request->input('imagens_para_remover');
            foreach ($imagensParaRemover as $imagemId) {
                $imagem = PostImagem::find($imagemId);
                if ($imagem && $imagem->post_id === $post->id) { // Garante que a imagem pertence a este post
                    Storage::disk('public')->delete($imagem->caminho_imagem); // Deleta do storage
                    $imagem->delete(); // Deleta do banco de dados
                }
            }
        }

        // 2. Adicionar novas imagens (se foram enviadas)
        if ($request->hasFile('imagens')) {
            foreach ($request->file('imagens') as $imagemFile) {
                $caminho = $imagemFile->store('posts', 'public'); // Salva no storage
                PostImagem::create([
                    'post_id' => $post->id,
                    'caminho_imagem' => $caminho,
                ]);
            }
        }

        return redirect()->back()->with('success', 'Post atualizado com sucesso!');
    }

    /**
     * Remove um post de portfólio.
     */
    public function destroy($id)
    {
        $post = PostPortfolio::findOrFail($id);

        // Autorização: Apenas o dono do portfólio pode apagar o post
        if (Auth::id() !== $post->portfolio->id_usuario) {
            abort(403, 'Você não tem permissão para apagar este post.');
        }

        // 1. Deletar imagens associadas do storage e do banco de dados
        foreach ($post->imagens as $imagem) {
            Storage::disk('public')->delete($imagem->caminho_imagem); // Deleta do storage
            $imagem->delete(); // Deleta do banco de dados (o relacionamento 'imagens' pode ter cascade, mas é mais seguro fazer manualmente)
        }

        // 2. Deletar o post em si
        $post->delete();

        return redirect()->back()->with('success', 'Post apagado com sucesso!');
    }

}
