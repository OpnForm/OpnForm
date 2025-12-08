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
        Schema::create('identity_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('domain'); // Email domain for routing users to this connection
            $table->string('type')->default('oidc'); // 'oidc' or 'saml' for future
            $table->string('issuer');
            $table->string('client_id');
            $table->text('client_secret'); // Use text to accommodate encrypted values
            $table->json('scopes')->nullable();
            $table->json('options')->nullable(); // Stores role mappings: { "group_role_mappings": [...] }
            $table->string('redirect_path')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->index(['workspace_id', 'enabled']);
            $table->index('slug');
            $table->index('domain');

            // Enforce single OIDC connection per workspace
            // Note: NULL workspace_id values are allowed multiple times (for global connections)
            // Only non-NULL workspace_id values are enforced to be unique per type
            $table->unique(['workspace_id', 'type'], 'unique_workspace_type');

            // Domain uniqueness: per workspace OR globally
            // For workspace-scoped: domain must be unique within that workspace
            // For global (NULL workspace_id): domain must be globally unique
            // This is enforced via application-level validation, as DB unique constraints
            // don't handle NULL workspace_id well (PostgreSQL treats NULLs as distinct)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('identity_connections');
    }
};
