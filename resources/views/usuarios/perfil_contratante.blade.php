<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Perfil - Contratante</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: #f9f9f9;
        }

        .profile-container {
            max-width: 800px;
            margin: 40px auto;
            background-color: #fff;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        .profile-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
        }

        .profile-header img {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid #a000ff;
        }

        .btn-purple {
            background-color: #a000ff;
            color: white;
            border: none;
        }

        .btn-purple:hover {
            background-color: #8200cc;
        }

        .form-section {
            margin-top: 20px;
        }

        .form-label {
            font-weight: 500;
        }
    </style>
</head>
<body>
    @include('Components.navbarbootstrap')

    <div class="profile-container">
        <form method="POST" action="{{ route('usuarios.update', $usuario->id) }}" enctype="multipart/form-data" data-address-form
              data-geocode-url="{{ route('endereco.localizar') }}" data-reverse-url="{{ route('endereco.reverso') }}">
            @csrf
            @method('PUT')

            <div class="profile-header">
                <img src="{{ \App\Support\MediaStorage::publicUrl($usuario->foto_perfil) ?: asset('img/default-user.png') }}" alt="Foto de perfil">

                <div>
                    <label class="form-label">Editar foto de perfil</label>
                    <input type="file" name="foto_perfil" class="form-control">
                </div>
            </div>

            <div class="form-section">
                <div class="mb-3">
                    <label class="form-label">Nome</label>
                    <input type="text" name="nome" class="form-control" value="{{ $usuario->nome }}">
                </div>

                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" value="{{ $usuario->email }}" disabled>
                </div>

                <div class="mb-3">
                    <label class="form-label">Cidade</label>
                    <input type="text" name="cidade" class="form-control" value="{{ $usuario->cidade }}">
                </div>

                @include('usuarios.partials.location_consent', ['locationConsentId' => 'solicitante-compartilhar-localizacao', 'locationUser' => $usuario])
                <input type="hidden" name="latitude" value="{{ $usuario->latitude }}">
                <input type="hidden" name="longitude" value="{{ $usuario->longitude }}">

                <div class="mb-3">
                    <label class="form-label">Endereço</label>
                    <input type="text" name="endereco" class="form-control" value="{{ $usuario->endereco }}">
                </div>

                <div class="mb-3">
                    <label class="form-label">Bairro</label>
                    <input type="text" name="bairro" class="form-control" value="{{ $usuario->bairro }}">
                </div>

                <div class="mb-3">
                    <label class="form-label">CEP</label>
                    <input type="text" name="cep" class="form-control" value="{{ $usuario->cep }}">
                </div>
                <div class="mb-3" data-location-map-panel @if(!in_array(old('compartilhar_localizacao', $usuario->compartilhar_localizacao), [1, '1', true], true)) hidden @endif>
                    <div class="address-map-card"><div class="address-map" data-address-map aria-label="Localização do trabalho"></div></div>
                    <div class="address-map-status" data-address-status aria-live="polite"></div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Nova Senha</label>
                    <input type="password" name="senha" class="form-control">
                </div>

                <div class="mb-3">
                    <label class="form-label">Confirmar Senha</label>
                    <input type="password" name="senha_confirmation" class="form-control">
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-purple px-4">Salvar alterações</button>
                </div>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
