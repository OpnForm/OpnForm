<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->text('submit_button_text')->nullable()->default(null)->change();
            $table->text('re_fill_button_text')->nullable()->default(null)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->text('submit_button_text')->nullable(false)->default(new Expression("('Submit')"))->change();
            $table->text('re_fill_button_text')->nullable(false)->default(new Expression("('Fill Again')"))->change();
        });
    }
};
