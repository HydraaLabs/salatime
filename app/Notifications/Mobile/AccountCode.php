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
            ->subject($this->purpose === 'verify' ? 'SalaTime — verify your email' : 'SalaTime — password reset')
            ->line($this->purpose === 'verify' ? 'Enter this code in SalaTime to verify your email address.' : 'Enter this code in SalaTime to reset your password.')
            ->line($this->code)
            ->line('This code expires in 15 minutes. If you did not request it, you can ignore this message.');

        return $this->usingAccountTransport($message);
    }
}
