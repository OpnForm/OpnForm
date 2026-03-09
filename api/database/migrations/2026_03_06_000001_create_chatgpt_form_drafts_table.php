<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('chatgpt_form_drafts', function (Blueprint $table) {
            $table->id();
            $table->string('gpt_chat_id')->unique();
            $table->json('form_state')->nullable();
            $table->unsignedInteger('draft_version')->default(1);
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('last_accessed_at')->nullable();
            $table->timestamp('handed_off_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatgpt_form_drafts');
    }
};
