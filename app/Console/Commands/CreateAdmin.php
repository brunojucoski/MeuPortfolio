<?php

namespace App\Console\Commands;

use App\Models\Usuario;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateAdmin extends Command
{
    protected $signature = 'app:create-admin';
    protected $description = 'Create an administrator interactively, without default passwords or public signup';

    public function handle(): int
    {
        if (!$this->input->isInteractive()) {
            $this->error('Use an interactive trusted terminal. Credentials must not be passed as command-line arguments.');
            return self::FAILURE;
        }
        $data = [
            'nome' => $this->ask('Nome'),
            'email' => mb_strtolower((string) $this->ask('E-mail')),
            'documento' => preg_replace('/\D/', '', (string) $this->secret('CPF ou CNPJ (oculto)')),
            'data_nasc' => $this->ask('Nascimento (AAAA-MM-DD)'),
            'senha' => $this->secret('Senha (minimo 12 caracteres)'),
            'senha_confirmation' => $this->secret('Confirme a senha'),
        ];
        $validator = Validator::make($data, [
            'nome' => 'required|string|max:200', 'email' => 'required|email|max:255|unique:usuarios,email',
            'documento' => 'required|digits_between:11,14|unique:usuarios,documento',
            'data_nasc' => 'required|date_format:Y-m-d|before:today',
            'senha' => 'required|string|min:12|confirmed',
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->keys() as $field) $this->error('Campo invalido: '.$field);
            return self::FAILURE;
        }
        $user = new Usuario(\Illuminate\Support\Arr::except($validator->validated(), ['senha']));
        $user->senha = Hash::make($data['senha']);
        $user->tipo_usuario = 1;
        $user->compartilhar_localizacao = false;
        $user->save();
        $this->info('Administrador criado. Utilize /login_interno.');
        return self::SUCCESS;
    }
}
