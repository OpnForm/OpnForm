<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop table if it exists (from failed migration) to ensure clean state
        Schema::dropIfExists('user_identities');

        Schema::create('user_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('connection_id')->constrained('identity_connections')->cascadeOnDelete();
            $table->string('subject');
            $table->string('email')->index();
            $table->json('claims')->nullable();
            $table->timestamps();

            $table->unique(['connection_id', 'subject']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_identities');
    }
};
