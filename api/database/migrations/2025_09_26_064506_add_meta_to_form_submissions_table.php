<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('form_submissions', 'meta')) {
            Schema::table('form_submissions', function (Blueprint $table) {
                $table->json('meta')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('form_submissions', 'meta')) {
            Schema::table('form_submissions', function (Blueprint $table) {
                $table->dropColumn('meta');
            });
        }
    }
};
