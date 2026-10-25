<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('form_submission_file_deletions', function (Blueprint $table) {
            $table->id();
            $table->text('path');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('next_attempt_at')->useCurrent();
            $table->timestamps();

            $table->index(['next_attempt_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_submission_file_deletions');
    }
};
