<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * Adds a composite index for polymorphic queries that filter by both type and id.
     * Keeps the existing versionable_id index for any direct ID lookups.
     */
    public function up(): void
    {
        Schema::table('versions', function (Blueprint $table) {
            // Add composite index for better query performance when filtering by both type and id
            // This is the primary index used by polymorphic relationships
            $table->index(['versionable_type', 'versionable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('versions', function (Blueprint $table) {
            $table->dropIndex(['versionable_type', 'versionable_id']);
        });
    }
};
