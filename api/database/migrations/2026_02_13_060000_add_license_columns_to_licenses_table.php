<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Add dedicated columns for self-hosted license management.
     * Previously these values were stored inside the `meta` JSON column,
     * but explicit columns improve query-ability and clarity.
     */
    public function up(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->timestamp('last_checked_at')->nullable()->after('meta');
            $table->timestamp('expires_at')->nullable()->after('last_checked_at');
            $table->json('features')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->dropColumn(['last_checked_at', 'expires_at', 'features']);
        });
    }
};
