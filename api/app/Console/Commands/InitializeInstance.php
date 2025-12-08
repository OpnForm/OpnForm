<?php

namespace App\Console\Commands;

use App\Enums\SettingsKey;
use App\Models\Setting;
use App\Service\Telemetry\TelemetryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class InitializeInstance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telemetry:initialize-instance';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Initialize instance ID and creation timestamp for telemetry tracking';

    /**
     * Execute the console command.
     */
    public function handle(TelemetryService $telemetryService): int
    {
        // Check if instance_id already exists
        $instanceId = Setting::get(SettingsKey::INSTANCE_ID);

        if ($instanceId) {
            $this->info('Instance ID already initialized: ' . $instanceId);
            return Command::SUCCESS;
        }

        // Generate UUID
        $uuid = (string) Str::uuid();

        // Store instance_id
        Setting::set(SettingsKey::INSTANCE_ID, $uuid);

        // Store instance_created_at timestamp
        Setting::set(SettingsKey::INSTANCE_CREATED_AT, now()->toIso8601String());

        // Clear cache so the new instance ID is picked up immediately
        Cache::forget('telemetry.instance_id');

        $this->info('Instance initialized successfully. Instance ID: ' . $uuid);

        // Identify instance in OpenPanel with version
        if ($telemetryService->shouldSendTelemetry()) {
            $client = $telemetryService->createClient();
            $version = $telemetryService->getAppVersion();
            $client->identifyInstance($uuid, $version);
        }

        // Track instance creation
        telemetry(\App\Service\Telemetry\TelemetryEvent::INSTANCE_CREATED);

        return Command::SUCCESS;
    }
}
