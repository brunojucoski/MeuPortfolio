@extends('layouts.app')



@section('content')
<div class="container">
    <h2>Cadastrar Novo Usuário</h2>

    <form method="POST" action="{{ route('usuarios.store') }}">
        @csrf

        <div class="mb-3">
            <label>Nome</label>
            <input type="text" name="nome" class="form-control" required>
        </div>

        <div class="mb-3">
            <label>Documento</label>
            <input type="text" name="documento" class="form-control" required>
        </div>

        <div class="mb-3">
            <label>Email</label>
            <input type="email" name="email" class="form-control" required>
        </div>

        <div class="mb-3">
            <label>Senha</label>
            <input type="password" name="senha" class="form-control" required>
        </div>

        <div class="mb-3">
            <label>Tipo de Usuário</label>
            <select name="tipo_usuario" class="form-control">
                @foreach($tipos as $tipo)
                    <option value="{{ $tipo->id }}">{{ \App\Support\NomenclaturaPublica::clientes($tipo->nome) }}</option>
                @endforeach
            </select>
        </div>

        <button class="btn btn-success">Salvar</button>
    </form>
</div>

@endsection
