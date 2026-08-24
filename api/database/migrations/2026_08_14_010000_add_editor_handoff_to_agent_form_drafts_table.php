<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('agent_form_drafts', function (Blueprint $table) {
            $table->char('handoff_token_hash', 64)->nullable()->unique();
            $table->timestamp('handoff_expires_at')->nullable()->index();
            $table->timestamp('handoff_consumed_at')->nullable();
            $table->char('editor_session_hash', 64)->nullable()->unique();
            $table->timestamp('editor_session_expires_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('agent_form_drafts', function (Blueprint $table) {
            $table->dropColumn([
                'handoff_token_hash',
                'handoff_expires_at',
                'handoff_consumed_at',
                'editor_session_hash',
                'editor_session_expires_at',
            ]);
        });
    }
};
