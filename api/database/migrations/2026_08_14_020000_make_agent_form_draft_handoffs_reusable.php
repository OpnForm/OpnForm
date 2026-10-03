<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('agent_form_draft_handoffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_form_draft_id')->constrained()->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });

        Schema::table('agent_form_drafts', function (Blueprint $table) {
            $table->dropUnique(['handoff_token_hash']);
            $table->dropIndex(['handoff_expires_at']);
            $table->dropColumn([
                'handoff_token_hash',
                'handoff_expires_at',
                'handoff_consumed_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('agent_form_drafts', function (Blueprint $table) {
            $table->char('handoff_token_hash', 64)->nullable()->unique();
            $table->timestamp('handoff_expires_at')->nullable()->index();
            $table->timestamp('handoff_consumed_at')->nullable();
        });

        Schema::dropIfExists('agent_form_draft_handoffs');
    }
};
