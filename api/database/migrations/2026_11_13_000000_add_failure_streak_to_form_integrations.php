<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('form_integrations', function (Blueprint $table) {
            $table->json('failure_streak')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('form_integrations', function (Blueprint $table) {
            $table->dropColumn('failure_streak');
        });
    }
};
