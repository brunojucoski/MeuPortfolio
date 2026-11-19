<?php

namespace Tests\Feature\Auth;

use App\Models\Usuario;
use Tests\Support\AuthTestCase;

class RegistrationTest extends AuthTestCase
{
    use \Tests\Support\ConfirmaCadastro;


    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertRedirect(route('usuarios.cadastro'));
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->cadastrarEConfirmar('/register', [
            'nome' => 'Pessoa de teste',
            'email' => 'test@example.com',
            'documento' => '12345678901', 'data_nasc' => '1990-01-01',
            'senha' => 'password', 'senha_confirmation' => 'password',
            'perfil_cadastro' => 'solicitante', 'aceite_termos' => 1,
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect('/perfil');
        $this->assertSame(3, (int) Usuario::sole()->tipo_usuario);
    }
}
