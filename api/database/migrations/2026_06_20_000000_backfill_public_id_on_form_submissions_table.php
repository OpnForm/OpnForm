<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('form_submissions')
            ->whereNull('public_id')
            ->chunkById(1000, function ($submissions) {
                foreach ($submissions as $submission) {
                    DB::table('form_submissions')
                        ->where('id', $submission->id)
                        ->whereNull('public_id')
                        ->update([
                            'public_id' => Str::uuid()->toString(),
                        ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
