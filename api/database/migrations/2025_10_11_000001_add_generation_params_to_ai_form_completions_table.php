<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('ai_form_completions', function (Blueprint $table) {
            $table->json('generation_params')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ai_form_completions', function (Blueprint $table) {
            $table->dropColumn('generation_params');
        });
    }
};
