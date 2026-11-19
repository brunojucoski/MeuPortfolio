<?php

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\Hash;
use Tests\Support\AuthTestCase;

class PasswordUpdateTest extends AuthTestCase
{

    public function test_password_can_be_updated(): void
    {
        $user = $this->usuario();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertTrue(Hash::check('new-password', $user->refresh()->senha));
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        $user = $this->usuario();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('updatePassword', 'current_password')
            ->assertRedirect('/profile');
    }
}
