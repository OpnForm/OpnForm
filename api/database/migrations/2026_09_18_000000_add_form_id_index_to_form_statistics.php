<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public $withinTransaction = false;

    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // A failed concurrent build can leave an invalid index behind.
            // Remove it before retrying instead of silently skipping it.
            $index = DB::selectOne("SELECT indisvalid FROM pg_index WHERE indexrelid = to_regclass('form_statistics_form_id_index')");
            if ($index && !$index->indisvalid) {
                DB::statement('DROP INDEX CONCURRENTLY form_statistics_form_id_index');
            }
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS form_statistics_form_id_index ON form_statistics (form_id)');

            return;
        }

        Schema::table('form_statistics', function (Blueprint $table) {
            $table->index('form_id');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS form_statistics_form_id_index');

            return;
        }

        Schema::table('form_statistics', function (Blueprint $table) {
            $table->dropIndex(['form_id']);
        });
    }
};
