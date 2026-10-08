

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sobre — MeuPortfólio</title>
    <link href="{{ asset('css/perfil.css') }}" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/sobre.css') }}" rel="stylesheet">
</head>
<body class="sobre-page-body">

@include('Components.navbarbootstrap')

<main class="sobre-page">
    <section class="container sobre-content">
        <img src="{{ $visualSistema->imagemUrl('sobre') }}" alt="Ilustração do MeuPortfólio" class="sobre-image">
        <div>
            <h1>{{ $visualSistema->sobreTitulo() }}</h1>
            <div class="sobre-texto">{{ $visualSistema->sobreTexto() }}</div>
        </div>
    </section>
</main>

@include('Components.footer')

</body>
</html>
