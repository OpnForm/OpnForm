<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ai_form_completions', fn (Blueprint $table) => $table->string('access_token_hash', 64)->nullable());
    }
    public function down(): void
    {
        Schema::table('ai_form_completions', fn (Blueprint $table) => $table->dropColumn('access_token_hash'));
    }
};
