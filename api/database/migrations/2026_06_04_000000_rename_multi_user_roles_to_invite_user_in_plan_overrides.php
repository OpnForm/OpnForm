<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    private const OLD_FEATURE = 'multi_user.roles';

    private const NEW_FEATURE = 'invite_user';

    private const MARKER_KEY = 'legacy_pro_grandfathering';

    public function up(): void
    {
        $this->renameFeature(self::OLD_FEATURE, self::NEW_FEATURE);
    }

    public function down(): void
    {
        $this->renameFeature(self::NEW_FEATURE, self::OLD_FEATURE);
    }

    private function renameFeature(string $from, string $to): void
    {
        DB::table('workspaces')
            ->whereNotNull('plan_overrides')
            ->orderBy('id')
            ->chunkById(100, function ($workspaces) use ($from, $to) {
                foreach ($workspaces as $workspace) {
                    $overrides = $this->decodeOverrides($workspace->plan_overrides ?? null);
                    $updated = $this->renameInOverrides($overrides, $from, $to);

                    if ($updated === $overrides) {
                        continue;
                    }

                    DB::table('workspaces')
                        ->where('id', $workspace->id)
                        ->update([
                            'plan_overrides' => json_encode($updated),
                        ]);
                }
            });
    }

    private function renameInOverrides(array $overrides, string $from, string $to): array
    {
        if (isset($overrides['features'])) {
            $overrides['features'] = $this->renameFeatureList($overrides['features'], $from, $to);
        }

        if (isset($overrides['permanent']['features'])) {
            $overrides['permanent']['features'] = $this->renameFeatureList(
                $overrides['permanent']['features'],
                $from,
                $to,
            );
        }

        if (isset($overrides[self::MARKER_KEY]['features'])) {
            $overrides[self::MARKER_KEY]['features'] = $this->renameFeatureList(
                $overrides[self::MARKER_KEY]['features'],
                $from,
                $to,
            );
        }

        return $overrides;
    }

    private function renameFeatureList(mixed $features, string $from, string $to): array
    {
        if (!is_array($features)) {
            return [];
        }

        $renamed = array_map(
            fn ($feature) => $feature === $from ? $to : $feature,
            $features,
        );

        return array_values(array_unique($renamed));
    }

    private function decodeOverrides(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!$value) {
            return [];
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }
};
