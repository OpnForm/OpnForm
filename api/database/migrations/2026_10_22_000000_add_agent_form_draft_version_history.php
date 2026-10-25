<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('agent_form_draft_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_form_draft_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->unsignedInteger('schema_version');
            $table->json('definition');
            $table->timestamps();
            $table->unique(['agent_form_draft_id', 'version']);
        });

        Schema::table('agent_form_draft_handoffs', function (Blueprint $table) {
            $table->unsignedInteger('expected_version')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('agent_form_draft_handoffs', function (Blueprint $table) {
            $table->dropColumn('expected_version');
        });

        Schema::dropIfExists('agent_form_draft_versions');
    }
};
