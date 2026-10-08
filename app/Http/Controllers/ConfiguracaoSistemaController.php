<?php

namespace App\Http\Controllers;

use App\Models\ConfiguracaoSistema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ConfiguracaoSistemaController extends Controller
{
    public function edit()
    {
        return view('admin.configuracoes_visuais');
    }

    public function update(Request $request)
    {
        $regras = [
            'cor_base' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'sobre_titulo' => ['required', 'string', 'max:120'],
            'sobre_texto' => ['required', 'string', 'max:15000'],
        ];
        $atributos = ['cor_base' => 'cor base', 'sobre_titulo' => 'título do Sobre', 'sobre_texto' => 'texto do Sobre'];
        foreach (config('visual-sistema.imagens') as $local => $imagem) {
            $regras['imagem_' . $local] = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=6000,max_height=6000'];
            $regras['restaurar_' . $local] = ['nullable', 'boolean'];
            $atributos['imagem_' . $local] = 'imagem de ' . $imagem['titulo'];
        }
        $validados = $request->validate($regras, [
            'image' => 'O campo :attribute deve ser uma imagem.',
            'mimes' => 'Use uma imagem JPG, PNG ou WebP em :attribute.',
            'max.file' => 'A imagem de :attribute deve ter até 2 MB.',
            'regex' => 'Informe uma cor hexadecimal válida no campo :attribute.',
            'dimensions' => 'A imagem de :attribute deve ter no máximo 6000 × 6000 pixels.',
        ], $atributos);
        $novas = [];
        $antigas = [];
        try {
            foreach (config('visual-sistema.imagens') as $local => $imagem) {
                if (!$request->boolean('restaurar_' . $local) && $request->hasFile('imagem_' . $local)) {
                    $caminho = $request->file('imagem_' . $local)->store('sistema/imagens', 'public');
                    if (!$caminho) throw new \RuntimeException('Não foi possível salvar a imagem.');
                    $novas['imagem_' . $local] = $caminho;
                }
            }
            DB::transaction(function () use ($request, $validados, $novas, &$antigas) {
                $configuracao = ConfiguracaoSistema::query()->lockForUpdate()->findOrFail(1);
                $dados = [
                    'cor_base' => strtolower($validados['cor_base']),
                    'sobre_titulo' => $validados['sobre_titulo'], 'sobre_texto' => $validados['sobre_texto'],
                    'atualizado_por' => $request->user()->id,
                ];
                foreach (config('visual-sistema.imagens') as $local => $imagem) {
                    $coluna = 'imagem_' . $local;
                    if ($request->boolean('restaurar_' . $local) || isset($novas[$coluna])) {
                        if ($configuracao->$coluna) $antigas[] = $configuracao->$coluna;
                        $dados[$coluna] = $request->boolean('restaurar_' . $local) ? null : $novas[$coluna];
                    }
                }
                $configuracao->update($dados);
            });
        } catch (Throwable $erro) {
            Storage::disk('public')->delete(array_values($novas));
            report($erro);
            return back()->withInput($request->except(array_map(fn ($local) => 'imagem_' . $local, array_keys(config('visual-sistema.imagens')))))
                ->withErrors(['configuracoes' => 'Não foi possível salvar as configurações. Tente novamente.']);
        }
        // Never delete bundled defaults, and remove replaced uploads only after commit.
        Storage::disk('public')->delete(array_filter($antigas, fn ($caminho) => str_starts_with($caminho, 'sistema/imagens/')));
        return redirect()->route('admin.configuracoes.edit')->with('success', 'Configurações visuais salvas.');
    }
}
