<?php

namespace Tests\Feature\Auth;

use App\Models\Usuario;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Tests\Support\AuthTestCase;

class PasswordResetTest extends AuthTestCase
{

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = $this->usuario();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = $this->usuario();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            $response = $this->get('/reset-password/'.$notification->token);

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = $this->usuario();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            $this->assertTrue(Hash::check('password', $user->fresh()->senha));
            $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);

            return true;
        });
    }

    public function test_reset_rotates_remember_token_changes_senha_and_consumes_token(): void
    {
        $user = $this->usuario(['remember_token' => 'previous-remember-token']);
        $token = Password::createToken($user);
        $payload = ['token' => $token, 'email' => $user->email, 'password' => 'new-password', 'password_confirmation' => 'new-password'];
        $this->post('/reset-password', $payload)->assertSessionHasNoErrors()->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('new-password', $user->fresh()->senha));
        $this->assertFalse(Hash::check('password', $user->fresh()->senha));
        $this->assertNotSame('previous-remember-token', $user->fresh()->remember_token);
        $this->post('/reset-password', $payload)->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $user->email, 'password' => 'new-password'])->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_expired_or_other_account_tokens_never_change_password(): void
    {
        $user = $this->usuario();
        $other = $this->usuario();
        $token = Password::createToken($user);
        foreach ([['email' => $user->email, 'token' => 'invalid'], ['email' => $other->email, 'token' => $token]] as $identity) {
            $this->post('/reset-password', $identity + ['password' => 'new-password', 'password_confirmation' => 'new-password'])->assertSessionHasErrors('email');
        }
        DB::table('password_reset_tokens')->where('email', $user->email)->update(['created_at' => now()->subMinutes(61)]);
        $this->post('/reset-password', ['email' => $user->email, 'token' => $token, 'password' => 'new-password', 'password_confirmation' => 'new-password'])
            ->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('password', $user->fresh()->senha));
        $this->assertTrue(Hash::check('password', $other->fresh()->senha));
    }

    public function test_recovery_does_not_reveal_accounts_or_send_to_internal_or_deleted_users(): void
    {
        Notification::fake();
        $active = $this->usuario();
        $deleted = $this->usuario(); $deleted->delete();
        $helper = $this->usuario(['email' => Usuario::EMAIL_VISITANTE_NAO_IDENTIFICADO]);
        $status = null;
        foreach ([$active->email, 'missing@example.test', $deleted->email, $helper->email, strtoupper($helper->email)] as $email) {
            $response = $this->post('/forgot-password', ['email' => $email])->assertSessionHasNoErrors()->assertSessionHas('status');
            $message = session('status');
            $this->assertSame($status ?? $message, $message);
            $status = $message;
        }
        Notification::assertSentTo($active, ResetPassword::class);
        Notification::assertNotSentTo($deleted, ResetPassword::class);
        Notification::assertNotSentTo($helper, ResetPassword::class);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $helper->email]);
    }

    public function test_internal_or_deleted_user_cannot_reset_even_with_preexisting_valid_token(): void
    {
        $helper = $this->usuario(['email' => Usuario::EMAIL_VISITANTE_NAO_IDENTIFICADO]);
        $deleted = $this->usuario();
        $tokens = [$helper->id => Password::createToken($helper), $deleted->id => Password::createToken($deleted)];
        $deleted->delete();
        foreach ([$helper, $deleted] as $user) {
            $this->post('/reset-password', ['email' => $user->email, 'token' => $tokens[$user->id], 'password' => 'new-password', 'password_confirmation' => 'new-password'])
                ->assertSessionHasErrors('email');
            $this->assertTrue(Hash::check('password', Usuario::withTrashed()->findOrFail($user->id)->senha));
        }
    }

    public function test_recovery_is_rate_limited_and_does_not_store_plain_tokens(): void
    {
        Notification::fake();
        $user = $this->usuario();
        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHasNoErrors();
        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $stored = DB::table('password_reset_tokens')->where('email', $user->email)->value('token');
            $this->assertNotSame($notification->token, $stored);
            $this->assertTrue(Hash::check($notification->token, $stored));
            return true;
        });
        for ($attempt = 1; $attempt < 6; $attempt++) {
            $this->post('/forgot-password', ['email' => 'missing@example.test'])->assertRedirect();
        }
        $this->post('/forgot-password', ['email' => 'missing@example.test'])->assertStatus(429);
    }

    public function test_real_mail_pipeline_renders_reset_url_and_recipient_without_network(): void
    {
        config(['mail.from.address' => 'notificacoes@meuportfolio.ink', 'mail.from.name' => 'MeuPortfolio']);
        URL::forceRootUrl('https://meuportfolio.ink');
        URL::forceScheme('https');
        $user = $this->usuario();
        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHasNoErrors();
        $message = Mail::mailer('array')->getSymfonyTransport()->messages()->sole()->getOriginalMessage();
        $this->assertSame($user->email, $message->getTo()[0]->getAddress());
        $this->assertSame('notificacoes@meuportfolio.ink', $message->getFrom()[0]->getAddress());
        $this->assertStringContainsString('https://meuportfolio.ink/reset-password/', $message->getHtmlBody());
        $this->assertStringNotContainsString($user->senha, $message->getHtmlBody());
    }

    public function test_resend_smtp_configuration_requires_tls_and_peer_verification_without_connecting(): void
    {
        $config = config('mail.mailers.smtp');
        $this->assertTrue($config['verify_peer']);
        $this->assertGreaterThan(0, $config['timeout']);
        config(['mail.mailers.smtp' => array_replace($config, [
            'scheme' => 'smtp', 'url' => null, 'host' => 'smtp.resend.com', 'port' => 587,
            'username' => 'resend', 'password' => 'non-secret-test-key', 'require_tls' => true,
        ])]);
        $transport = Mail::mailer('smtp')->getSymfonyTransport();
        $this->assertInstanceOf(EsmtpTransport::class, $transport);
        $this->assertTrue($transport->isTlsRequired());
        $this->assertTrue($transport->isAutoTls());
        $this->assertSame('resend', $transport->getUsername());
        $this->assertSame('smtp.resend.com', $transport->getStream()->getHost());
        $this->assertSame(587, $transport->getStream()->getPort());
        $this->assertNotSame(false, $transport->getStream()->getStreamOptions()['ssl']['verify_peer'] ?? true);
    }
}
