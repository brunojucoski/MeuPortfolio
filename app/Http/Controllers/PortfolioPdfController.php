<?php

namespace App\Http\Controllers;

use App\Models\PortfolioArtista;
use App\Support\PortfolioPdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PortfolioPdfController extends Controller
{
    public function store(Request $request, PortfolioArtista $portfolio, PortfolioPdf $exporter)
    {
        abort_unless((int) $request->user()->tipo_usuario === 2
            && (int) $portfolio->id_usuario === (int) $request->user()->id, 403);

        $selection = $request->validate([
            'campos' => ['nullable', 'array', 'max:20'],
            'campos.*' => ['string', 'distinct', Rule::in(array_keys(PortfolioPdf::FIELDS))],
            'redes' => ['nullable', 'array', 'max:6'],
            'redes.*' => ['string', 'distinct', Rule::in(['instagram', 'personal', 'tiktok', 'github', 'linkedin', 'whatsapp'])],
            'albuns' => ['nullable', 'array', 'max:100'],
            'albuns.*' => ['integer', 'distinct', Rule::exists('categorias_posts_portfolio', 'id')->where('id_portfolio_artista', $portfolio->id)],
            'posts' => ['nullable', 'array', 'max:100'],
            'posts.*' => ['integer', 'distinct', Rule::exists('posts_portfolio', 'id')->where('id_portfolio', $portfolio->id)->whereNull('deleted_at')],
        ]);
        if (!array_filter($selection)) {
            throw ValidationException::withMessages(['exportacao' => 'Selecione pelo menos um item para exportar.']);
        }
        $portfolio->setRelation('usuario', $request->user());
        $lock = Cache::lock('portfolio-pdf:' . $portfolio->id, 120);
        if (!$lock->get()) return response()->json(['message' => 'Já existe uma exportação em andamento. Aguarde e tente novamente.'], 409);
        try {
            $pdf = $exporter->generate($portfolio, $selection);
        } finally {
            $lock->release();
        }

        // Avoid placing deselected personal information in filenames or PDF metadata.
        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="portfolio.pdf"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
