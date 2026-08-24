<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('agent_form_drafts', function (Blueprint $table) {
            $table->id();
            $table->char('token_hash', 64)->unique();
            $table->json('definition');
            $table->unsignedSmallInteger('schema_version');
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 20)->default('active')->index();
            $table->foreignId('claimed_form_id')->nullable()->constrained('forms')->nullOnDelete();
            $table->char('claim_receipt_hash', 64)->nullable()->unique();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_form_drafts');
    }
};
