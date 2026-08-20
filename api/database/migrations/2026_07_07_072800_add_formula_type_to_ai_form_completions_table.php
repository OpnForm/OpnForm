<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            Schema::table('ai_form_completions', function (Blueprint $table) {
                $table->enum('type', ['form', 'fields', 'formula'])->default('form')->change();
            });
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE ai_form_completions DROP CONSTRAINT ai_form_completions_type_check');
            DB::statement("ALTER TABLE ai_form_completions ADD CONSTRAINT ai_form_completions_type_check CHECK (type::text = ANY (ARRAY['form'::text, 'fields'::text, 'formula'::text]))");
        } else {
            Schema::table('ai_form_completions', function (Blueprint $table) {
                $table->string('type')->default('form')->change();
            });
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            Schema::table('ai_form_completions', function (Blueprint $table) {
                $table->enum('type', ['form', 'fields'])->default('form')->change();
            });
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE ai_form_completions DROP CONSTRAINT ai_form_completions_type_check');
            DB::statement("ALTER TABLE ai_form_completions ADD CONSTRAINT ai_form_completions_type_check CHECK (type::text = ANY (ARRAY['form'::text, 'fields'::text]))");
        }
    }
};
