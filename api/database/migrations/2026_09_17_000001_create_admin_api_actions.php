<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('admin_api_actions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('actor_id')->index();
            $table->unsignedBigInteger('token_id')->index();
            $table->string('operation', 64);
            $table->string('target', 255);
            $table->char('idempotency_hash', 64);
            $table->char('request_hash', 64);
            $table->text('payload'); // Encrypted cast: reasons and customer details never enter queue payloads.
            $table->string('status', 32);
            $table->json('result')->nullable();
            $table->timestamps();
            $table->unique(['token_id', 'idempotency_hash']);
        });
        Schema::create('admin_api_action_locks', function (Blueprint $table) {
            $table->string('target', 255)->primary();
            $table->uuid('action_id')->unique();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_api_action_locks');
        Schema::dropIfExists('admin_api_actions');
    }
};
