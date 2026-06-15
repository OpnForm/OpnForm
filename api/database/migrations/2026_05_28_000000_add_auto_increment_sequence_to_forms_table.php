<?php

use App\Models\Forms\FormSubmission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('forms', 'auto_increment_sequence')) {
            Schema::table('forms', function (Blueprint $table) {
                $table->unsignedBigInteger('auto_increment_sequence')->default(0);
            });
        }

        $completedStatus = FormSubmission::STATUS_COMPLETED;

        DB::table('forms')->orderBy('id')->chunkById(100, function ($forms) use ($completedStatus) {
            foreach ($forms as $form) {
                if ((int) $form->auto_increment_sequence !== 0) {
                    continue;
                }

                $count = DB::table('form_submissions')
                    ->where('form_id', $form->id)
                    ->where('status', $completedStatus)
                    ->count();

                if ($count === 0) {
                    continue;
                }

                DB::table('forms')
                    ->where('id', $form->id)
                    ->update(['auto_increment_sequence' => $count]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('forms', 'auto_increment_sequence')) {
            Schema::table('forms', function (Blueprint $table) {
                $table->dropColumn('auto_increment_sequence');
            });
        }
    }
};
