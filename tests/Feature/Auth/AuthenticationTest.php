<?php

namespace Tests\Feature\Auth;

use App\Models\Usuario;
use App\Providers\RouteServiceProvider;
use Tests\Support\AuthTestCase;

class AuthenticationTest extends AuthTestCase
{

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = $this->usuario();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(RouteServiceProvider::HOME);
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = $this->usuario();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = $this->usuario();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    public function test_internal_visitor_helper_cannot_log_in_even_with_its_password(): void
    {
        $this->usuario(['email' => Usuario::EMAIL_VISITANTE_NAO_IDENTIFICADO]);
        foreach ([Usuario::EMAIL_VISITANTE_NAO_IDENTIFICADO, strtoupper(Usuario::EMAIL_VISITANTE_NAO_IDENTIFICADO)] as $email) {
            $this->post('/login', ['email' => $email, 'password' => 'password'])->assertSessionHasErrors('password');
            $this->assertGuest();
        }
    }

    public function test_inactive_account_cannot_log_in_and_attempts_are_limited(): void
    {
        $user = $this->usuario();
        $user->delete();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('password');
        }
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
