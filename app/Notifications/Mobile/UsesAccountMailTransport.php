<?php

namespace App\Notifications\Mobile;

use Illuminate\Notifications\Messages\MailMessage;

trait UsesAccountMailTransport
{
    protected function usingAccountTransport(MailMessage $message): MailMessage
    {
        $transport = config('mail.mailers.mobile_accounts', []);
        foreach (['host', 'username', 'password'] as $required) {
            if (! is_string($transport[$required] ?? null) || $transport[$required] === '') {
                return $message;
            }
        }
        $sender = $transport['from']['address'] ?? null;
        if (is_string($sender) && filter_var($sender, FILTER_VALIDATE_EMAIL)) {
            $message->mailer('mobile_accounts')
                ->from($sender, $transport['from']['name'] ?? 'SalaTime');
        }

        return $message;
    }
}
