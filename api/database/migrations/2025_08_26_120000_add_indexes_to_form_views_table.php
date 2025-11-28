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
        Schema::table('form_views', function (Blueprint $table) {
            // Composite index matches WHERE form_id = ? AND created_at BETWEEN ... queries
            $table->index(['form_id', 'created_at'], 'form_views_form_id_created_at_index');
            // Keep single-column index for cleanup jobs on created_at alone
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('form_views', function (Blueprint $table) {
            $table->dropIndex('form_views_form_id_created_at_index');
            $table->dropIndex(['created_at']);
        });
    }
};
