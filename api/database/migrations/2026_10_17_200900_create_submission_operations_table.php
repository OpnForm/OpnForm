<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('submission_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
            $table->string('operation_key', 80);
            $table->string('payload_hash', 64);
            $table->foreignId('submission_id')->nullable()->constrained('form_submissions')->cascadeOnDelete();
            $table->json('processed_data')->nullable();
            $table->json('submission_meta')->nullable();
            $table->json('temporary_paths')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('outbox_state', 20)->default('none');
            $table->uuid('claim_token')->nullable();
            $table->timestamps();
            $table->unique(['form_id', 'operation_key']);
            $table->index(['outbox_state', 'updated_at']);
        });
        Schema::create('submission_effects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operation_id')->constrained('submission_operations')->cascadeOnDelete();
            $table->foreignId('integration_id')->constrained('form_integrations')->cascadeOnDelete();
            $table->string('state', 20)->default('pending');
            $table->uuid('claim_token')->nullable();
            $table->string('exception_type')->nullable();
            $table->timestamps();
            $table->unique(['operation_id', 'integration_id']);
            $table->index(['state', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_effects');
        Schema::dropIfExists('submission_operations');
    }
};
