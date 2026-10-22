<?php

namespace App\Support;

use App\Models\PortfolioArtista;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PortfolioPdf
{
    private int $imageBytes = 0;
    public const FIELDS = [
        'nome' => 'Nome', 'foto' => 'Foto de perfil', 'nome_artistico' => 'Nome artístico',
        'descricao' => 'Descrição do portfólio', 'areas' => 'Áreas de atuação',
        'email' => 'E-mail', 'telefone' => 'Telefone', 'cidade' => 'Cidade',
        'endereco' => 'Rua / endereço', 'bairro' => 'Bairro', 'cep' => 'CEP',
        'data_nasc' => 'Data de nascimento', 'documento' => 'CPF / CNPJ',
    ];

    public function generate(PortfolioArtista $portfolio, array $selection): string
    {
        $data = $this->documentData($portfolio, $selection);
        $cover = $this->renderer();
        $cover->loadHtml(view('portfolio.pdf.document', $data + ['coverOnly' => true])->render(), 'UTF-8');
        $cover->render();
        if ($cover->getCanvas()->get_page_count() !== 1) {
            throw ValidationException::withMessages(['exportacao' => 'Os dados selecionados não cabem na capa. Desmarque alguns dados ou reduza a descrição do portfólio antes de exportar.']);
        }
        $pdf = $cover;
        if ($data['pages']) {
            unset($pdf, $cover);
            $pdf = $this->renderer();
            $pdf->loadHtml(view('portfolio.pdf.document', $data + ['coverOnly' => false])->render(), 'UTF-8');
            $pdf->render();
        }
        $canvas = $pdf->getCanvas();
        $canvas->page_script(function ($page, $total, $canvas, $fonts) {
            $font = $fonts->getFont('DejaVu Sans', 'normal');
            $canvas->line(42, 802, 553, 802, [0.85, 0.86, 0.88], 0.5);
            $canvas->text(42, 810, 'Portfólio', $font, 8, [0.35, 0.36, 0.39]);
            $text = $page . ' / ' . $total;
            $canvas->text(553 - $fonts->getTextWidth($text, $font, 8), 810, $text, $font, 8, [0.35, 0.36, 0.39]);
        });
        return $pdf->output();
    }

    private function renderer(): Dompdf
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('isJavascriptEnabled', false);
        $options->set('allowedProtocols', ['data://' => ['rules' => []]]);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isFontSubsettingEnabled', true);
        $pdf = new Dompdf($options);
        $pdf->setPaper('A4', 'portrait');
        return $pdf;
    }

    public function documentData(PortfolioArtista $portfolio, array $selection): array
    {
        $this->imageBytes = 0;
        $user = $portfolio->usuario;
        $fields = array_fill_keys($selection['campos'] ?? [], true);
        $values = [];
        foreach (self::FIELDS as $key => $label) {
            if (!isset($fields[$key]) || in_array($key, ['foto', 'areas'], true)) continue;
            $value = in_array($key, ['nome_artistico', 'descricao'], true) ? $portfolio->{$key} : $user->{$key};
            if ($key === 'data_nasc' && $value) $value = $value->format('d/m/Y');
            if (trim((string) $value) !== '') $values[$key] = ['label' => $label, 'value' => (string) $value];
        }
        if (isset($fields['areas'])) {
            $names = $user->categoriasArtisticas()->orderBy('nome')->pluck('nome')->all();
            if ($names) $values['areas'] = ['label' => self::FIELDS['areas'], 'value' => implode(' · ', $names)];
        }
        $links = array_values(array_filter(PortfolioSocialLinks::forProfile($portfolio, $user->telefone),
            fn ($link) => in_array($link['network'], $selection['redes'] ?? [], true)));
        $albums = $portfolio->categoriasPostsPortfolio()->get();
        $albumIds = $selection['albuns'] ?? [];
        $postIds = $selection['posts'] ?? [];
        $posts = ($albumIds || $postIds) ? $portfolio->posts()->where(function ($query) use ($albumIds, $postIds) {
            $query->whereIn('id', $postIds)->orWhereIn('id_categoria_post_portfolio', $albumIds);
        })->with('imagens')->limit(101)->get() : collect();
        if ($posts->count() > 100 || $posts->sum(fn ($post) => $post->imagens->count()) > 120) {
            throw ValidationException::withMessages(['exportacao' => 'Selecione até 100 posts e 120 imagens por PDF. Exporte os demais trabalhos em outro documento.']);
        }
        $groups = [];
        foreach ($albums as $album) {
            $groupPosts = $posts->where('id_categoria_post_portfolio', $album->id);
            if ($groupPosts->isNotEmpty() || in_array($album->id, $albumIds)) {
                $groups[] = ['title' => $album->nome, 'posts' => $groupPosts];
            }
        }
        $loose = $posts->filter(fn ($post) => !$albums->contains('id', $post->id_categoria_post_portfolio));
        if ($loose->isNotEmpty()) $groups[] = ['title' => 'Trabalhos avulsos', 'posts' => $loose];
        $pages = [];
        foreach ($groups as $group) {
            if ($group['posts']->isEmpty()) $pages[] = ['album' => $group['title'], 'post' => null, 'description' => '', 'images' => [], 'continuation' => false];
            foreach ($group['posts'] as $post) {
                $descriptions = $this->textChunks((string) $post->descricao);
                $images = $post->imagens->values();
                $imageIndex = 0;
                $first = true;
                do {
                    $description = array_shift($descriptions) ?? '';
                    $compact = mb_strlen($description) <= 350 && mb_strlen((string) $post->nome) <= 160 && mb_strlen($group['title']) <= 160;
                    $limit = $description !== '' && !$compact ? 1 : 2;
                    $pageImages = [];
                    for ($i = 0; $i < $limit && $imageIndex < $images->count(); $i++, $imageIndex++) {
                        $pageImages[] = ['image' => $this->image($images[$imageIndex]->caminho_imagem), 'number' => $imageIndex + 1, 'total' => $images->count()];
                    }
                    $pages[] = ['album' => $group['title'], 'post' => $post->nome, 'description' => $description, 'images' => $pageImages, 'continuation' => !$first];
                    $first = false;
                } while ($descriptions || $imageIndex < $images->count());
            }
        }
        if (count($pages) > 200 || array_sum(array_map(fn ($value) => mb_strlen($value['value']), $values)) > 12000) {
            throw ValidationException::withMessages(['exportacao' => 'O conteúdo selecionado é muito extenso. Exporte menos itens por documento.']);
        }
        return [
            'values' => $values, 'links' => $links, 'pages' => $pages,
            'photo' => isset($fields['foto']) ? $this->image($user->foto_perfil) : null,
            'accent' => preg_match('/^#[0-9a-f]{6}$/i', (string) $portfolio->cor_primaria_portfolio)
                ? $portfolio->cor_primaria_portfolio : app(VisualSistema::class)->corBase(),
        ];
    }

    private function textChunks(string $text): array
    {
        if (mb_strlen($text) > 50000) {
            throw ValidationException::withMessages(['exportacao' => 'A descrição de um post é muito extensa para exportar. Reduza-a e tente novamente.']);
        }
        $text = trim(str_replace(["\r\n", "\r"], "\n", $text));
        $chunks = [];
        // Bound both characters and explicit lines so each sheet keeps room for its image.
        while ($text !== '') {
            $part = mb_substr($text, 0, 900);
            $lines = explode("\n", $part);
            if (count($lines) > 14) $part = implode("\n", array_slice($lines, 0, 14));
            if (mb_strlen($text) > mb_strlen($part) && mb_strlen($part) > 700) {
                $space = mb_strrpos($part, ' ');
                if ($space !== false && $space > 700) $part = mb_substr($part, 0, $space);
            }
            $chunks[] = $part;
            $text = ltrim(mb_substr($text, mb_strlen($part)));
        }
        return $chunks;
    }

    private function image(?string $relative): ?array
    {
        if (!$relative || str_contains($relative, "\0") || preg_match('~(^|[/\\\\])\.\.([/\\\\]|$)|^[a-z]+:~i', $relative)) return null;
        $root = realpath(Storage::disk('public')->path(''));
        $path = realpath(Storage::disk('public')->path($relative));
        if (!$root || !$path || !str_starts_with(strtolower($path), strtolower($root . DIRECTORY_SEPARATOR))
            || !is_file($path) || filesize($path) > 20 * 1024 * 1024) return null;
        $size = @getimagesize($path);
        if (!$size || $size[0] * $size[1] > 25000000 || !in_array($size[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true)) return null;
        $source = @imagecreatefromstring(file_get_contents($path));
        if (!$source) return null;
        $scale = min(1, 1400 / $size[0], 1800 / $size[1]);
        $width = max(1, (int) round($size[0] * $scale));
        $height = max(1, (int) round($size[1] * $scale));
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imagecopyresampled($image, $source, 0, 0, 0, 0, $width, $height, $size[0], $size[1]);
        ob_start();
        imagejpeg($image, null, 85);
        $bytes = ob_get_clean();
        imagedestroy($image);
        imagedestroy($source);
        $this->imageBytes += strlen($bytes);
        if ($this->imageBytes > 12 * 1024 * 1024) {
            throw ValidationException::withMessages(['exportacao' => 'As imagens selecionadas são muito grandes. Exporte menos trabalhos por documento.']);
        }
        return ['src' => 'data:image/jpeg;base64,' . base64_encode($bytes), 'width' => $width, 'height' => $height];
    }
}
