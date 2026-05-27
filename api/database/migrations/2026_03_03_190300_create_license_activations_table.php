<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('license_activations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_key_id')->constrained('license_keys')->cascadeOnDelete();
            $table->string('instance_id')->index();
            $table->string('status')->default('active');
            $table->json('usage')->nullable();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamps();

            $table->unique(['license_key_id', 'instance_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_activations');
    }
};
