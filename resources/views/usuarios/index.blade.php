@extends('layouts.app')

@section('content')
<div class="container mt-4">
    <h2 class="mb-3">Lista de Usuários</h2>
    <a href="{{ route('usuarios.create') }}" class="btn btn-primary mb-3">Novo Usuário</a>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Nome</th>
                <th>Email</th>
                <th>Tipo</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($usuarios as $usuario)
                <tr>
                    <td>{{ $usuario->nome }}</td>
                    <td>{{ $usuario->email }}</td>
                    <td>{{ \App\Support\NomenclaturaPublica::clientes($usuario->tipo->nome ?? 'Sem tipo') }}</td>
                    <td>
                        <a href="{{ route('usuarios.edit', $usuario->id) }}" class="btn btn-sm btn-warning">Editar</a>
                        
                        <form action="{{ route('usuarios.destroy', $usuario->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Tem certeza?')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-danger">Excluir</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
