<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Query\Expression;

return new class () extends Migration {
    public function up(): void
    {
        $driver = config('database.default');

        Schema::table('forms', function (Blueprint $table) use ($driver) {
            if ($driver === 'mysql') {
                $table->json('cover_settings')->default(new Expression('(JSON_OBJECT())'))->nullable(true);
            } else {
                $table->json('cover_settings')->default('{}')->nullable(true);
            }
        });
    }

    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->dropColumn('cover_settings');
        });
    }
};
