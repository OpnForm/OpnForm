<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('ai_form_completions', function (Blueprint $table) {
            $table->foreignIdFor(User::class)->nullable()->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ai_form_completions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
