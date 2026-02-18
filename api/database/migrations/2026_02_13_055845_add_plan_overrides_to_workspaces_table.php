<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * Adds plan_overrides JSON column for admin-granted tier/feature/limit overrides.
     * Example usage:
     * - Override tier: {"tier": "business"}
     * - Override limits: {"limits": {"file_upload_size": 100000000}}
     * - Grant features: {"features": ["sso.oidc", "analytics_dashboard"]}
     */
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->json('plan_overrides')->nullable()->after('settings');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn('plan_overrides');
        });
    }
};
