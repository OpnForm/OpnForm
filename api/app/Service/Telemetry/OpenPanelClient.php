<?php

namespace App\Service\Telemetry;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenPanelClient
{
    public function __construct(
        private string $endpoint,
        private ?string $clientId,
        private ?string $clientSecret
    ) {
    }

    /**
     * Identify an instance as a user in OpenPanel.
     *
     * @param string $profileId The instance ID to use as profile ID
     * @param string|null $version The OpnForm version
     * @param array $properties Additional properties to identify
     * @return bool
     */
    public function identifyInstance(
        string $profileId,
        ?string $version = null,
        array $properties = []
    ): bool {
        if (!$this->clientId || !$this->clientSecret) {
            Log::warning('Telemetry skipped: missing client credentials');
            return false;
        }

        try {
            $identifyProperties = $properties;
            if ($version) {
                $identifyProperties['opnform_version'] = $version;
            }

            $payload = [
                'type' => 'identify',
                'payload' => [
                    'profileId' => $profileId,
                    'properties' => $identifyProperties,
                ],
            ];

            $response = $this->sendRequest($payload);

            if (!$response->successful()) {
                Log::warning('Telemetry identify failed to send', [
                    'profileId' => $profileId,
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);

                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::warning('Telemetry identify error', [
                'profileId' => $profileId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send an event to OpenPanel.
     *
     * @param string $eventName
     * @param array $properties
     * @param string $profileId The instance ID to use as profileId to link event to identified user
     * @return bool
     */
    public function sendEvent(
        string $eventName,
        array $properties,
        string $profileId
    ): bool {
        if (!$this->clientId || !$this->clientSecret) {
            Log::warning('Telemetry skipped: missing client credentials');
            return false;
        }

        if (!$profileId) {
            Log::warning('Telemetry skipped: profileId not found');
            return false;
        }

        try {
            $payload = [
                'type' => 'track',
                'payload' => [
                    'name' => $eventName,
                    'properties' => $properties,
                    'profileId' => $profileId,
                ],
            ];

            $response = $this->sendRequest($payload);

            if (!$response->successful()) {
                Log::warning('Telemetry event failed to send', [
                    'event' => $eventName,
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);

                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::warning('Telemetry event error', [
                'event' => $eventName,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send a request to the OpenPanel API.
     *
     * @param array $payload
     * @return \Illuminate\Http\Client\Response
     */
    private function sendRequest(array $payload)
    {
        return Http::timeout(5)
            ->withHeaders([
                'openpanel-client-id' => $this->clientId,
                'openpanel-client-secret' => $this->clientSecret,
            ])
            ->post($this->endpoint, $payload);
    }
}
