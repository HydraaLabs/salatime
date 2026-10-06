<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mobile_accounts', function (Blueprint $table) {
            $table->string('locale', 2)->nullable();
        });

        // Keep the existing preference documents, versions and timestamps intact.
        // Accounts without a recognized historical choice stay nullable.
        $supported = ['en', 'fr', 'ar', 'tr', 'ur', 'id', 'ms', 'es', 'bn', 'fa'];
        DB::table('mobile_preferences')->select('mobile_account_id', 'preferences')
            ->chunkById(500, function ($rows) use ($supported) {
                foreach ($rows as $row) {
                    $preferences = json_decode($row->preferences, true, 32);
                    $language = is_array($preferences) ? ($preferences['language'] ?? null) : null;
                    if (! is_string($language) || ! preg_match('/^[a-z]{2,3}(?:[-_][a-z0-9]{2,8})*$/i', trim($language))) {
                        continue;
                    }
                    $language = preg_split('/[-_]/', strtolower(trim($language)))[0];
                    if (in_array($language, $supported, true)) {
                        DB::table('mobile_accounts')->where('id', $row->mobile_account_id)
                            ->whereNull('locale')->update(['locale' => $language]);
                    }
                }
            }, 'mobile_account_id');
    }

    public function down(): void
    {
        Schema::table('mobile_accounts', function (Blueprint $table) {
            $table->dropColumn('locale');
        });
    }
};
