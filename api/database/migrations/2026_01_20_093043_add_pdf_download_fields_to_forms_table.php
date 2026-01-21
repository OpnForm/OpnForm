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
        Schema::table('forms', function (Blueprint $table) {
            $table->boolean('pdf_download_enabled')->default(false)->after('editable_submissions_button_text');
            $table->string('pdf_download_button_text')->nullable()->after('pdf_download_enabled');
            $table->foreignId('pdf_integration_id')->nullable()->after('pdf_download_button_text');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->dropColumn(['pdf_download_enabled', 'pdf_download_button_text', 'pdf_integration_id']);
        });
    }
};
