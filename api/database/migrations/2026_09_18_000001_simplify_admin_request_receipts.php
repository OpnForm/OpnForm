<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('admin_api_action_locks');
        Schema::table('admin_api_actions', fn (Blueprint $table) => $table->dropColumn('payload'));
    }
    public function down(): void
    {
        Schema::table('admin_api_actions', fn (Blueprint $table) => $table->text('payload')->nullable());
        Schema::create('admin_api_action_locks', function (Blueprint $table) {
            $table->string('target', 255)->primary();
            $table->uuid('action_id')->unique();
        });
    }
};
