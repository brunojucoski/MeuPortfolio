<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CodigoCadastro extends Notification
{
    public const VALIDADE_MINUTOS = 10;

    public function __construct(public readonly string $codigo) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Confirme seu cadastro no MeuPortfólio')
            ->greeting('Confirme seu e-mail')
            ->line('Digite este código na tela de cadastro do MeuPortfólio:')
            ->line($this->codigo)
            ->line('O código vale por '.self::VALIDADE_MINUTOS.' minutos. Não o compartilhe com ninguém.')
            ->line('Se você não iniciou este cadastro, ignore esta mensagem. Nenhuma conta será criada.')
            ->salutation('Equipe MeuPortfólio');
    }
}
