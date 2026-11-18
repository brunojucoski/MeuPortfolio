@extends('layouts.app')

@section('content')
<div class="container mt-4">
    <h2>Editar Perfil - {{ $usuario->nome }}</h2>

    <form action="{{ route('usuarios.update', $usuario->id) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="mb-3">
            <label for="nome" class="form-label">Nome</label>
            <input type="text" name="nome" id="nome" value="{{ old('nome', $usuario->nome) }}" class="form-control">
        </div>

        <div class="mb-3">
            <label for="email" class="form-label">E-mail</label>
            <input type="email" name="email" id="email" value="{{ old('email', $usuario->email) }}" class="form-control">
        </div>

        <div class="mb-3">
            <label for="tipo_usuario_id" class="form-label">Tipo de Usuário</label>
            <select name="tipo_usuario_id" class="form-control">
                @foreach ($tiposUsuario as $tipo)
                    <option value="{{ $tipo->id }}" {{ $usuario->tipo_usuario_id == $tipo->id ? 'selected' : '' }}>
                        {{ \App\Support\NomenclaturaPublica::clientes($tipo->nome) }}
                    </option>
                @endforeach
            </select>
        </div>

        <button type="submit" class="btn btn-success">Salvar Alterações</button>
    </form>
</div>
@endsection
