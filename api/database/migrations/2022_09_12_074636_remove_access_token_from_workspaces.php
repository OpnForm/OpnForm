<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_workspace', function (Blueprint $table) {
            $table->dropColumn('access_token');
            $table->dropColumn('is_owner');
            $table->string('role')->default('admin');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_workspace', function (Blueprint $table) {
            $table->string('access_token')->nullable();
            $table->boolean('is_owner')->default(true);
            $table->dropColumn('role');
        });
    }
};
