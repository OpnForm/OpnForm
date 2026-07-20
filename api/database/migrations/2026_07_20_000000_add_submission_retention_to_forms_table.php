<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->unsignedSmallInteger('submission_retention_value')->nullable();
            $table->string('submission_retention_unit', 10)->nullable();
        });

        Schema::table('form_submissions', function (Blueprint $table) {
            $table->index(['form_id', 'updated_at'], 'form_submissions_form_id_updated_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('form_submissions', function (Blueprint $table) {
            $table->dropIndex('form_submissions_form_id_updated_at_index');
        });

        Schema::table('forms', function (Blueprint $table) {
            $table->dropColumn([
                'submission_retention_value',
                'submission_retention_unit',
            ]);
        });
    }
};
