<?php

namespace Tests\Feature;

use Tests\Support\AuthTestCase;

class ProfileTest extends AuthTestCase
{

    public function test_profile_page_is_displayed(): void
    {
        $user = $this->usuario();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk()->assertSee('name="nome"', false)->assertSee($user->nome);
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = $this->usuario();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'nome' => 'Pessoa de teste',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Pessoa de teste', $user->nome);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = $this->usuario();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'nome' => 'Pessoa de teste',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = $this->usuario();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertSoftDeleted('usuarios', ['id' => $user->id]);
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = $this->usuario();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }

    public function test_email_uniqueness_uses_usuarios_and_does_not_allow_reserved_identity(): void
    {
        $user = $this->usuario();
        $other = $this->usuario();
        foreach ([$other->email, \App\Models\Usuario::EMAIL_VISITANTE_NAO_IDENTIFICADO] as $email) {
            $this->actingAs($user)->patch('/profile', ['nome' => 'Alterado', 'email' => $email])->assertSessionHasErrors('email');
            $this->assertSame($user->email, $user->fresh()->email);
        }
        $other->delete();
        $this->actingAs($user)->patch('/profile', ['nome' => 'Alterado', 'email' => $other->email])->assertSessionHasErrors('email');
    }

    public function test_profile_does_not_mass_assign_role_password_or_google_identity(): void
    {
        $user = $this->usuario();
        $this->actingAs($user)->patch('/profile', [
            'nome' => 'Alterado', 'email' => $user->email, 'tipo_usuario' => 1, 'senha' => 'unhashed', 'google_id' => 'forged',
        ])->assertSessionHasNoErrors();
        $user->refresh();
        $this->assertSame('Alterado', $user->nome);
        $this->assertSame(3, (int) $user->tipo_usuario);
        $this->assertNull($user->google_id);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('password', $user->senha));
    }
}
