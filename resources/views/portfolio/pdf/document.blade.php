<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Portfólio</title>
    <style>
        @page { margin: 42pt 42pt 55pt; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10pt; line-height: 1.5; color: #25292e; }
        * { box-sizing: border-box; word-wrap: break-word; }
        .cover { border-top: 5pt solid {{ $accent }}; padding-top: 24pt; }
        .kicker { font-size: 9pt; color: #616973; margin: 0 0 14pt; }
        .cover h1 { font-size: 32pt; line-height: 1.2; margin: 0 0 14pt; }
        .cover h2 { font-size: 22pt; line-height: 1.3; margin: 0 0 6pt; overflow-wrap: anywhere; }
        .cover .name { color: #616973; margin: 0 0 16pt; font-size: 12pt; }
        .identity { width: 100%; border-collapse: collapse; margin-bottom: 20pt; table-layout: fixed; }
        .identity td { padding: 0; vertical-align: top; }
        .portrait-cell { width: 90pt; text-align: right; }
        .portrait { border-radius: 5pt; }
        .section-title { color: #616973; font-size: 9pt; font-weight: bold; margin: 16pt 0 6pt; }
        .bio { margin: 0 0 14pt; font-size: 10pt; overflow-wrap: anywhere; }
        .details { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .details td { width: 50%; vertical-align: top; padding: 5pt 12pt 5pt 0; border-bottom: 0.5pt solid #e6e8eb; overflow-wrap: anywhere; }
        .label { font-size: 8pt; color: #616973; }
        .links { margin-top: 16pt; }
        .links p { margin: 5pt 0; font-size: 9pt; }
        a { color: #25292e; text-decoration: underline; overflow-wrap: anywhere; }
        .work { page-break-before: always; border-top: 3pt solid {{ $accent }}; padding-top: 16pt; }
        .album { font-size: 11pt; color: #616973; margin: 0 0 8pt; overflow-wrap: anywhere; }
        .work h2 { font-size: 19pt; line-height: 1.3; margin: 0 0 12pt; overflow-wrap: anywhere; }
        .description { font-size: 10pt; line-height: 1.5; margin: 0 0 14pt; overflow-wrap: anywhere; }
        .image-block { text-align: center; margin: 0 0 14pt; page-break-inside: avoid; }
        .image-block img { display: block; margin: 0 auto; }
        .caption { font-size: 8pt; color: #616973; margin-top: 5pt; }
        .unavailable { height: 140pt; line-height: 140pt; background: #f2f3f5; color: #616973; font-size: 10pt; text-align: center; }
        .muted { color: #616973; }
    </style>
</head>
<body>
    <section class="cover">
        <p class="kicker">APRESENTAÇÃO</p>
        <table class="identity"><tr><td>
            <h1>Portfólio</h1>
            @if(isset($values['nome_artistico']))<h2>{{ $values['nome_artistico']['value'] }}</h2>@endif
            @if(isset($values['nome']))<p class="name">{{ $values['nome']['value'] }}</p>@endif
        </td>
        @if($photo)
            @php
                $scale = min(80 / $photo['width'], 95 / $photo['height']);
            @endphp
            <td class="portrait-cell"><img class="portrait" src="{{ $photo['src'] }}" style="width: {{ round($photo['width'] * $scale, 2) }}pt; height: {{ round($photo['height'] * $scale, 2) }}pt" alt="Foto de perfil"></td>
        @endif
        </tr></table>
        @if(isset($values['descricao']))
            <p class="section-title">SOBRE O TRABALHO</p>
            <p class="bio">{!! nl2br(e($values['descricao']['value'])) !!}</p>
        @endif
        @php
            $details = collect($values)->except(['nome', 'nome_artistico', 'descricao'])->values();
        @endphp
        @if($details->isNotEmpty())
            <table class="details">
                @foreach($details->chunk(2) as $row)
                    <tr>@foreach($row as $item)<td><span class="label">{{ $item['label'] }}</span><br>{{ $item['value'] }}</td>@endforeach</tr>
                @endforeach
            </table>
        @endif
        @if($links)
            <div class="links"><p class="section-title">REDES E CONTATO</p>
                @foreach($links as $link)<p><strong>{{ $link['label'] }}</strong><br><a href="{{ $link['url'] }}">{{ $link['url'] }}</a></p>@endforeach
            </div>
        @endif
    </section>
    @if(!$coverOnly)
        @foreach($pages as $page)
            <section class="work">
                <p class="album">{{ $page['album'] }}</p>
                <h2>{{ $page['post'] ?? $page['album'] }}</h2>
                @if($page['continuation'])<p class="caption">Continuação</p>@endif
                @if($page['description'] !== '')<p class="description">{!! nl2br(e($page['description'])) !!}</p>@endif
                @foreach($page['images'] as $item)
                    <div class="image-block">
                        @if($item['image'])
                            @php
                                $imageHeight = count($page['images']) === 2 ? ($page['description'] !== '' ? 225 : 260)
                                    : (mb_strlen($page['description']) <= 350 && mb_strlen((string) $page['post']) <= 160 && mb_strlen($page['album']) <= 160 ? 480 : 260);
                                $scale = min(505 / $item['image']['width'], $imageHeight / $item['image']['height']);
                            @endphp
                            <img src="{{ $item['image']['src'] }}" style="width: {{ round($item['image']['width'] * $scale, 2) }}pt; height: {{ round($item['image']['height'] * $scale, 2) }}pt" alt="Imagem do trabalho">
                        @else
                            <div class="unavailable">Imagem indisponível</div>
                        @endif
                        <p class="caption">Imagem {{ $item['number'] }} de {{ $item['total'] }}</p>
                    </div>
                @endforeach
                @if(!$page['post'])<p class="muted">Este álbum ainda não possui posts.</p>@endif
            </section>
        @endforeach
    @endif
</body>
</html>
