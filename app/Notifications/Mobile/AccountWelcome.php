<?php

namespace App\Notifications\Mobile;

use App\Services\Mobile\AccountLocale;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Lang;

class AccountWelcome extends Notification
{
    use UsesAccountMailTransport;

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $locale = AccountLocale::forAccount($notifiable);
        $copy = Lang::get('mobile_welcome', [], $locale);

        return $this->usingAccountTransport((new MailMessage)
            ->subject($copy['subject'])
            ->view(['html' => 'mail.mobile.welcome', 'text' => 'mail.mobile.welcome-text'], [
                'name' => trim((string) $notifiable->name),
                'siteUrl' => 'https://salatime.net',
                'locale' => $locale,
                'direction' => in_array($locale, ['ar', 'fa', 'ur'], true) ? 'rtl' : 'ltr',
                'copy' => $copy,
            ]));
    }
}
