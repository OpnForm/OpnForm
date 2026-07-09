<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE ai_form_completions DROP CONSTRAINT ai_form_completions_type_check');
        DB::statement("ALTER TABLE ai_form_completions ADD CONSTRAINT ai_form_completions_type_check CHECK (type IN ('form', 'fields', 'formula'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE ai_form_completions DROP CONSTRAINT ai_form_completions_type_check');
        DB::statement("ALTER TABLE ai_form_completions ADD CONSTRAINT ai_form_completions_type_check CHECK (type IN ('form', 'fields'))");
    }
};
