<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mobile_accounts', function (Blueprint $table) {
            $table->unsignedBigInteger('reading_progress_revision')->default(0);
        });

        Schema::create('mobile_reading_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mobile_account_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16);
            $table->string('item_key', 96);
            $table->date('day');
            $table->unsignedSmallInteger('count');
            $table->unsignedBigInteger('revision');
            $table->timestamps();
            $table->unique(['mobile_account_id', 'kind', 'item_key', 'day'], 'mobile_reading_entry_unique');
            $table->unique(['mobile_account_id', 'revision'], 'mobile_reading_revision_unique');
        });

        Schema::create('mobile_reading_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mobile_account_id')->constrained()->cascadeOnDelete();
            $table->uuid('operation_id');
            $table->foreignId('entry_id')->constrained('mobile_reading_entries')->cascadeOnDelete();
            $table->unsignedSmallInteger('count');
            $table->unsignedBigInteger('revision');
            $table->timestamp('created_at');
            $table->unique(['mobile_account_id', 'operation_id'], 'mobile_reading_operation_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_reading_operations');
        Schema::dropIfExists('mobile_reading_entries');
        Schema::table('mobile_accounts', function (Blueprint $table) {
            $table->dropColumn('reading_progress_revision');
        });
    }
};
