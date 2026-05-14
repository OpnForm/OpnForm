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
        Schema::table('workspaces', function (Blueprint $table) {
            $table
                ->foreignId('plan_overrides_subscription_id')
                ->nullable()
                ->after('plan_overrides')
                ->constrained('subscriptions')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropForeign(['plan_overrides_subscription_id']);
            $table->dropColumn('plan_overrides_subscription_id');
        });
    }
};
