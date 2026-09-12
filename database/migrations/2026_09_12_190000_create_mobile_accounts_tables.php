<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('email', 254)->unique();
            $table->string('password')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();
        });
        Schema::create('mobile_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mobile_account_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 16);
            $table->string('subject', 255);
            $table->string('client_id', 255)->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'subject']);
            $table->unique(['mobile_account_id', 'provider']);
        });
        Schema::create('mobile_account_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mobile_account_id')->constrained()->cascadeOnDelete();
            $table->string('purpose', 16);
            $table->string('token_hash', 64);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->unique(['mobile_account_id', 'purpose']);
        });
        Schema::create('mobile_preferences', function (Blueprint $table) {
            $table->foreignId('mobile_account_id')->primary()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('version')->default(0);
            $table->json('preferences');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_preferences');
        Schema::dropIfExists('mobile_account_actions');
        Schema::dropIfExists('mobile_identities');
        Schema::dropIfExists('mobile_accounts');
    }
};
