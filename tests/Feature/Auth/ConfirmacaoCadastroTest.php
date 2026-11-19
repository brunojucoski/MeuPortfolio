<?php

namespace Tests\Feature\Auth;

use App\Models\Usuario;
use App\Notifications\CodigoCadastro;
use App\Support\ConfirmacaoCadastro;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\Support\AuthTestCase;

class ConfirmacaoCadastroTest extends AuthTestCase
{
    private function iniciar(): void
    {
        $this->post('/cadastro', [
            'nome' => 'Cliente teste', 'email' => 'conta@example.test',
            'documento' => '12345678901', 'data_nasc' => '1990-01-01',
            'senha' => 'password', 'senha_confirmation' => 'password',
            'perfil_cadastro' => 'solicitante', 'aceite_termos' => 1,
        ])->assertRedirect(route('cadastro.confirmacao'));
        $this->assertGuest();
        $this->assertDatabaseCount('usuarios', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    private function codigo(): string
    {
        return Notification::sent(new AnonymousNotifiable, CodigoCadastro::class)->last()->codigo;
    }

    public function test_envia_email_renderizado_e_mostra_modal_sem_expor_codigo(): void
    {
        $this->iniciar();
        $email = Mail::mailer('array')->getSymfonyTransport()->messages()->sole()->getOriginalMessage();
        $this->assertSame('conta@example.test', $email->getTo()[0]->getAddress());
        $this->assertSame(1, preg_match('/\b[1-9][0-9]{5}\b/', $email->getTextBody(), $matches));
        $this->get(route('cadastro.confirmacao'))->assertOk()->assertSee('confirmação')
            ->assertSee('<dialog', false)->assertSee('conta@example.test')->assertDontSee($matches[0]);
        $this->post(route('cadastro.confirmar'), ['codigo' => $matches[0]])->assertRedirect(route('perfil'));
        $this->assertAuthenticatedAs(Usuario::sole());
        $this->assertNotNull(Usuario::sole()->email_verified_at);
        $this->assertFalse(session()->has(ConfirmacaoCadastro::SESSAO));
    }

    public function test_reenvio_apos_tres_minutos_substitui_o_codigo_anterior(): void
    {
        Notification::fake();
        $this->freezeTime();
        $this->iniciar();
        $primeiro = $this->codigo();
        $this->travel(179)->seconds();
        $this->post(route('cadastro.reenviar'))->assertSessionHasErrors('envio');
        Notification::assertSentOnDemandTimes(CodigoCadastro::class, 1);
        $this->travel(1)->seconds();
        $this->post(route('cadastro.reenviar'))->assertRedirect(route('cadastro.confirmacao'))->assertSessionHasNoErrors();
        Notification::assertSentOnDemandTimes(CodigoCadastro::class, 2);
        $this->post(route('cadastro.confirmar'), ['codigo' => $primeiro])->assertSessionHasErrors('codigo');
        $this->assertGuest();
        $this->post(route('cadastro.confirmar'), ['codigo' => $this->codigo()])->assertRedirect(route('perfil'));
    }

    public function test_bloqueia_apos_cinco_erros_e_permite_novo_codigo(): void
    {
        Notification::fake();
        $this->iniciar();
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('cadastro.confirmar'), ['codigo' => '000000'])->assertSessionHasErrors('codigo');
        }
        $this->post(route('cadastro.confirmar'), ['codigo' => $this->codigo()])->assertSessionHasErrors('codigo');
        $this->assertGuest();
        $this->travel(3)->minutes();
        $this->post(route('cadastro.reenviar'))->assertSessionHasNoErrors();
        $this->post(route('cadastro.confirmar'), ['codigo' => $this->codigo()])->assertRedirect(route('perfil'));
    }

    public function test_codigo_e_cadastro_temporario_expiram(): void
    {
        Notification::fake();
        $this->iniciar();
        $this->travel(10)->minutes();
        $this->post(route('cadastro.confirmar'), ['codigo' => $this->codigo()])->assertSessionHasErrors('codigo');
        $this->travel(21)->minutes();
        $this->post(route('cadastro.reenviar'))->assertRedirect(route('usuarios.cadastro'));
        $this->assertGuest();
        $this->assertDatabaseCount('usuarios', 0);
    }

    public function test_outra_sessao_nao_pode_reusar_codigo_nem_contornar_intervalo(): void
    {
        Notification::fake();
        $this->iniciar();
        $primeiro = $this->codigo();
        session()->invalidate();
        $this->iniciar();
        Notification::assertSentOnDemandTimes(CodigoCadastro::class, 1);
        $this->post(route('cadastro.confirmar'), ['codigo' => $primeiro])->assertSessionHasErrors('codigo');
        $this->assertGuest();
        $this->assertDatabaseCount('usuarios', 0);
    }

    public function test_falha_no_transporte_nao_cria_conta_e_pode_tentar_novamente(): void
    {
        Notification::shouldReceive('send')->once()->andThrow(new \Symfony\Component\Mailer\Exception\TransportException('SMTP indisponível'));
        $this->iniciar();
        $this->get(route('cadastro.confirmacao'))->assertOk()->assertSee('Não foi possível confirmar o envio')
            ->assertDontSee('Código enviado.');
        $this->travel(3)->minutes();
        Notification::fake();
        $this->post(route('cadastro.reenviar'))->assertSessionHasNoErrors();
        $this->post(route('cadastro.confirmar'), ['codigo' => $this->codigo()])->assertRedirect(route('perfil'));
    }
}
