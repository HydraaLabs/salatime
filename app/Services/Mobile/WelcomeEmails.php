<?php

namespace App\Services\Mobile;

use App\Models\Mobile\MobileAccount;
use App\Notifications\Mobile\AccountWelcome;
use Illuminate\Support\Facades\DB;

class WelcomeEmails
{
    public function afterRegistration(MobileAccount $account): void
    {
        // Sign-ins and provider links for existing accounts must never resend it.
        if (! $account->wasRecentlyCreated || ! config('mobile_auth.mail_enabled')) {
            return;
        }
        $accountId = $account->getKey();

        // A rolled-back social registration must not leave an email to send.
        DB::afterCommit(function () use ($accountId) {
            $handled = false;
            app()->terminating(function () use ($accountId, &$handled) {
                if ($handled) {
                    return;
                }
                $handled = true;
                if (! config('mobile_auth.mail_enabled')) {
                    return;
                }

                try {
                    // Reload only after the response; deleted accounts are skipped.
                    MobileAccount::find($accountId)?->notify(new AccountWelcome);
                } catch (\Throwable $error) {
                    // SMTP failure must not affect the account or disclose its address.
                    report(new \RuntimeException('SalaTime welcome email could not be delivered.'));
                }
            });
        });
    }
}
