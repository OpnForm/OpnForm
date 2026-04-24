<?php

namespace App\Service\Forms;

use App\Http\Requests\UserFormRequest;
use App\Http\Resources\FormResource;
use App\Models\Forms\Form;
use App\Models\Workspace;
use App\Service\Billing\PlanAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Stevebauman\Purify\Facades\Purify;

use function collect;

class FormCleaner
{
    /**
     * All the performed cleanings
     *
     * @var array
     */
    private array $cleanings = [];

    private array $data;

    // For remove keys those have empty value
    private array $customKeys = ['seo_meta'];

    private array $cleaningMessages = [
        // For form
        'no_branding' => 'OpenForm branding is not hidden.',
        'database_fields_update' => 'Form submission will only create new records (no updates).',
        'editable_submissions' => 'Users will not be able to edit their submissions.',
        'custom_code' => 'Custom code was disabled',
        'analytics' => 'Analytics was disabled',
        'custom_css' => 'Custom CSS was disabled',
        'seo_meta' => 'Custom SEO was disabled',
        'redirect_url' => 'Redirect Url was disabled',
        'enable_partial_submissions' => 'Partial submissions were disabled',
        'enable_ip_tracking' => 'IP tracking was disabled',

        // For fields
        'file_upload' => 'Link field is not a file upload.',
        'allowed_file_types' => 'Custom file type restrictions were disabled.',
        'custom_block' => 'The custom block was removed.',
        'secret_input' => 'Secret input was disabled.',
    ];

    protected PlanAccessService $planAccessService;

    // Policy toggles for current cleaning run
    private bool $allowCustomCode = true;

    /**
     * Returns form data after request ingestion
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Returns true if at least one cleaning was done
     */
    public function hasCleaned(): bool
    {
        return count($this->cleanings) > 0;
    }

    /**
     * Returns the messages for each cleaning step performed
     */
    public function getPerformedCleanings(): array
    {
        $cleaningMsgs = [];
        foreach ($this->cleanings as $key => $val) {
            $cleaningMsgs[$key] = collect($val)->map(function ($cleaning) {
                return $this->cleaningMessages[$cleaning];
            });
        }

        return $cleaningMsgs;
    }

    /**
     * Returns the raw cleaning keys for internal logic.
     */
    public function getCleaningKeys(): array
    {
        return $this->cleanings;
    }

    /**
     * Removes form pro features from data if user isn't pro
     */
    public function processRequest(UserFormRequest $request): FormCleaner
    {
        $data = $request->validated();
        $customDomain = $data['custom_domain'] ?? $request->form?->custom_domain ?? null;
        $allowOnSelfHosted = config('app.self_hosted', true) && (bool) config('opnform.custom_code.enable_self_hosted', false);
        $this->allowCustomCode = !empty($customDomain) || $allowOnSelfHosted;
        $this->data = $this->commonCleaning($data);

        return $this;
    }

    /**
     * Create form cleaner instance from existing form
     */
    public function processForm(Request $request, Form $form): FormCleaner
    {
        $data = (new FormResource($form))->toArray($request);

        // Determine if custom code is allowed in this response context
        $allowOnSelfHosted = config('app.self_hosted', true) && (bool) config('opnform.custom_code.enable_self_hosted', false);
        $hasCustomDomain = !empty($data['custom_domain'] ?? null);
        $this->allowCustomCode = $hasCustomDomain || $allowOnSelfHosted;

        // Suppress top-level custom_code if not allowed (silent, not recorded as cleaning)
        if (!$this->allowCustomCode) {
            $data['custom_code'] = null;
        }

        // Single pass over properties: sanitize text blocks and optionally remove nf-code blocks
        $this->data = $this->commonCleaning($data);

        return $this;
    }

    private function getPlanAccessService(): PlanAccessService
    {
        if (!isset($this->planAccessService)) {
            $this->planAccessService = app(PlanAccessService::class);
        }

        return $this->planAccessService;
    }

    /**
     * Dry run cleanings
     */
    public function simulateCleaning(Workspace $workspace): FormCleaner
    {
        $this->data = $this->cleanForTier($workspace, $this->data, true);

        return $this;
    }

    /**
     * Perform cleanings based on workspace feature access
     */
    public function performCleaning(Workspace $workspace): FormCleaner
    {
        $this->data = $this->cleanForTier($workspace, $this->data, false);

        return $this;
    }

    /**
     * Clean form data based on the workspace's effective feature access.
     */
    private function cleanForTier(Workspace $workspace, array $data, bool $simulation = false): array
    {
        $planAccessService = $this->getPlanAccessService();

        $formFeatures = config('plans.form_features', []);
        $formDefaults = config('plans.form_feature_defaults', []);

        // Clean form-level features
        foreach (array_keys($formFeatures) as $feature) {
            if (!$planAccessService->hasFormFeature($workspace, $feature)) {
                $defaultValue = $formDefaults[$feature] ?? null;
                $this->cleanFeature($data, $feature, $defaultValue, $simulation);
            }
        }

        // Clean field-level features
        if (isset($data['properties']) && is_array($data['properties'])) {
            foreach ($data['properties'] as &$property) {
                $this->cleanFieldForTier($workspace, $property, $planAccessService, $simulation);
            }
            unset($property);
        }

        return $data;
    }

    /**
     * Clean a specific feature from form data.
     */
    private function cleanFeature(array &$data, string $feature, mixed $defaultValue, bool $simulation = false): void
    {
        $formVal = Arr::get($data, $feature);

        // Transform customkeys values
        $formVal = $this->cleanCustomKeys($feature, $formVal);

        // Transform boolean values
        $formVal = (($formVal === 0 || $formVal === '0') ? false : $formVal);
        $formVal = (($formVal === 1 || $formVal === '1') ? true : $formVal);

        if (!is_null($formVal) && $formVal !== $defaultValue) {
            if (!isset($this->cleanings['form'])) {
                $this->cleanings['form'] = [];
            }
            $this->cleanings['form'][] = $feature;

            if (!$simulation) {
                Arr::set($data, $feature, $defaultValue);
            }
        }
    }

    /**
     * Clean field-level features based on tier.
     */
    private function cleanFieldForTier(Workspace $workspace, array &$property, PlanAccessService $planAccessService, bool $simulation = false): void
    {
        // secret_input requires pro
        if (isset($property['secret_input']) && $property['secret_input'] === true) {
            if (!$planAccessService->hasFormFeature($workspace, 'secret_input')) {
                $this->cleanings[$property['name']][] = 'secret_input';
                if (!$simulation) {
                    $property['secret_input'] = false;
                }
            }
        }

        if (!empty($property['allowed_file_types']) && !$planAccessService->hasFeature($workspace, 'file_upload.allowed_types')) {
            $this->cleanings[$property['name']][] = 'allowed_file_types';
            if (!$simulation) {
                $property['allowed_file_types'] = null;
            }
        }
    }

    /**
     * Clean all forms:
     * - Escape html of custom text block
     */
    private function commonCleaning(array $data)
    {
        if (!empty($data['properties']) && is_array($data['properties'])) {
            foreach ($data['properties'] as $index => &$property) {
                if (($property['type'] ?? null) === 'nf-text' && isset($property['content'])) {
                    $property['content'] = Purify::clean($property['content']);
                }

                if (!$this->allowCustomCode && ($property['type'] ?? null) === 'nf-code') {
                    unset($data['properties'][$index]);
                }
            }
            unset($property);
            $data['properties'] = array_values($data['properties']);
        }

        return $data;
    }

    // Remove keys those have empty value
    private function cleanCustomKeys($key, $formVal)
    {
        if (in_array($key, $this->customKeys) && $formVal !== null) {
            $newVal = [];
            foreach ($formVal as $k => $val) {
                if ($val) {
                    $newVal[$k] = $val;
                }
            }

            return $newVal;
        }

        return $formVal;
    }
}
