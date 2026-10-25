<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('form_submission_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_submission_id')
                ->constrained('form_submissions')
                ->cascadeOnDelete();
            $table->text('path');
            $table->char('path_hash', 64);
            $table->timestamps();

            $table->unique(['form_submission_id', 'path_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_submission_files');
    }
};
