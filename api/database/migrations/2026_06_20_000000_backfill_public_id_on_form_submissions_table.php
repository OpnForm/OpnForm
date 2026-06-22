<?php

use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Keep deployment migrations lightweight. Legacy submissions receive a
        // public_id lazily when an edit/share URL is generated.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
