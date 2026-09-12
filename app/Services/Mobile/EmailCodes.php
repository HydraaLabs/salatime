<?php

namespace App\Services\Mobile;

use App\Models\Mobile\MobileAccount;
use App\Notifications\Mobile\AccountCode;
use Illuminate\Support\Facades\DB;

class EmailCodes
{
    public function send(MobileAccount $account, string $purpose): void
    {
        if (! config('mobile_auth.mail_enabled')) {
            return;
        }
        $code = (string) random_int(100000, 999999);
        DB::table('mobile_account_actions')->updateOrInsert(
            ['mobile_account_id' => $account->id, 'purpose' => $purpose],
            ['token_hash' => $this->hash($code), 'attempts' => 0, 'expires_at' => now()->addMinutes(15), 'created_at' => now(), 'updated_at' => now()]
        );
        $account->notify(new AccountCode($code, $purpose));
    }

    /** Five attempts in total per code, with a row lock across concurrent requests. */
    public function consume(MobileAccount $account, string $purpose, string $code): bool
    {
        return DB::transaction(function () use ($account, $purpose, $code) {
            $query = DB::table('mobile_account_actions')->where('mobile_account_id', $account->id)->where('purpose', $purpose);
            $row = (clone $query)->lockForUpdate()->first();
            if (! $row || $row->attempts >= 5 || now()->greaterThanOrEqualTo($row->expires_at)) {
                return false;
            }
            if (! hash_equals($row->token_hash, $this->hash($code))) {
                $query->increment('attempts');

                return false;
            }
            $query->delete();

            return true;
        });
    }

    private function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}
