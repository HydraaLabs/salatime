<?php

namespace App\Notifications\Mobile;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountCode extends Notification
{
    use UsesAccountMailTransport;

    public function __construct(public readonly string $code, public readonly string $purpose) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject($this->purpose === 'verify' ? 'SalaTime — Vérifiez votre adresse e-mail' : 'SalaTime — Réinitialisez votre mot de passe')
            ->view(['html' => 'mail.mobile.code', 'text' => 'mail.mobile.code-text'], [
                'name' => trim((string) ($notifiable->name ?? '')),
                'code' => $this->code,
                'verify' => $this->purpose === 'verify',
                'siteUrl' => 'https://salatime.net',
            ]);

        return $this->usingAccountTransport($message);
    }
}
