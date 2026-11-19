<?php

namespace Tests\Support;

use App\Notifications\CodigoCadastro;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

trait ConfirmaCadastro
{
    private function cadastrarEConfirmar(string $url, array $dados): TestResponse
    {
        Notification::fake();
        $this->post($url, $dados)->assertRedirect(route('cadastro.confirmacao'))->assertSessionHasNoErrors();
        $this->assertGuest();
        $notificacao = Notification::sent(new AnonymousNotifiable, CodigoCadastro::class)->sole();
        return $this->post(route('cadastro.confirmar'), ['codigo' => $notificacao->codigo]);
    }
}
