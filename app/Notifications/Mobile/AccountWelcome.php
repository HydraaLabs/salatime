<?php

namespace App\Notifications\Mobile;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountWelcome extends Notification
{
    use UsesAccountMailTransport;

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return $this->usingAccountTransport((new MailMessage)
            ->subject('Bienvenue sur SalaTime')
            ->view(['html' => 'mail.mobile.welcome', 'text' => 'mail.mobile.welcome-text'], [
                'name' => trim((string) $notifiable->name),
                'siteUrl' => 'https://salatime.net',
            ]));
    }
}
