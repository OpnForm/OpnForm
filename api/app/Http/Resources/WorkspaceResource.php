<?php

namespace App\Http\Resources;

use App\Service\Billing\BillingStateResolver;
use App\Service\Billing\PlanAccessService;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkspaceResource extends JsonResource
{
    public static $wrap = null;

    /**
     * When true, only expose minimal public fields (for guests/non-members).
     */
    private bool $restrictForGuest = false;

    /**
     * Enable guest-restricted output for this resource instance.
     */
    public function restrictForGuest(): self
    {
        $this->restrictForGuest = true;

        return $this;
    }

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        if ($this->restrictForGuest) {
            // Minimal public shape: include settings with custom code for form rendering
            $settings = $this->settings ?? [];
            return [
                'id' => $this->resource->id,
                'max_file_size' => $this->resource->max_file_size / 1000000,
                'settings' => $this->resource->hasFeature('branding.advanced') ? [
                    'custom_code' => $settings['custom_code'] ?? null,
                    'custom_css' => $settings['custom_css'] ?? null,
                ] : [],
            ];
        }

        $data = array_merge(parent::toArray($request), [
            'max_file_size' => $this->max_file_size / 1000000,
            'is_readonly' => $this->isReadonlyUser($request->user()),
            'is_admin' => $this->isAdminUser($request->user()),
            'users_count' => $this->users_count,
            'plan_tier' => app(PlanAccessService::class)->getTier($this->resource),
            'features' => app(PlanAccessService::class)->getFeatures($this->resource),
            'limits' => app(PlanAccessService::class)->getLimits($this->resource),
            'required_tiers' => app(PlanAccessService::class)->getRequiredTiers(),
            'is_grandfathered' => app(BillingStateResolver::class)->resolveWorkspace($this->resource)->isGrandfathered,
        ]);

        return $data;
    }
}
