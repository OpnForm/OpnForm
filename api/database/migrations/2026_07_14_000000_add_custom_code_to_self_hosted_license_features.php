<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        DB::table('license_keys')
            ->where('plan', 'self_hosted')
            ->chunkById(1000, function ($licenseKeys): void {
                foreach ($licenseKeys as $licenseKey) {
                    $features = json_decode($licenseKey->features ?? '[]', true) ?: [];

                    if (($features['custom_code'] ?? false) === true) {
                        continue;
                    }

                    $features['custom_code'] = true;

                    DB::table('license_keys')
                        ->where('id', $licenseKey->id)
                        ->update([
                            'features' => json_encode($features),
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        // Existing licenses may have been issued with this entitlement, so leave it intact on rollback.
    }
};
