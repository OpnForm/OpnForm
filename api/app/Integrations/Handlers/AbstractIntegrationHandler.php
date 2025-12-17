<?php

namespace App\Integrations\Handlers;

use App\Models\Integration\FormIntegration;
use App\Events\Forms\FormSubmitted;
use App\Models\Forms\Form;
use App\Models\Integration\FormIntegrationsEvent;
use App\Service\Forms\FormSubmissionFormatter;
use App\Service\Forms\FormLogicConditionChecker;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Vinkla\Hashids\Facades\Hashids;

abstract class AbstractIntegrationHandler
{
    protected $form = null;
    protected $submissionData = null;
    protected $integrationData = null;
    protected $provider = null;

    public function __construct(
        protected FormSubmitted $event,
        protected FormIntegration $formIntegration,
        protected array $integration
    ) {
        $this->form = $event->form;
        $this->submissionData = $event->data;
        $this->integrationData = $formIntegration->data;
        $this->provider = $formIntegration->provider;
    }

    protected function getProviderName(): string
    {
        return $this->integration['name'] ?? '';
    }

    protected function logicConditionsMet(): bool
    {
        if (!$this->formIntegration->logic || empty((array) $this->formIntegration->logic)) {
            return true;
        }
        return FormLogicConditionChecker::conditionsMet(
            json_decode(json_encode($this->formIntegration->logic), true),
            $this->submissionData
        );
    }

    protected function shouldRun(): bool
    {
        return $this->logicConditionsMet();
    }

    protected function getWebhookUrl(): ?string
    {
        return '';
    }

    /**
     * Default webhook payload. Can be changed in child classes.
     */
    protected function getWebhookData(): array
    {
        return self::formatWebhookData($this->form, $this->submissionData);
    }

    final public function run(): void
    {
        try {
            $this->handle();
            $this->formIntegration->events()->create([
                'status' => FormIntegrationsEvent::STATUS_SUCCESS,
            ]);
        } catch (\Exception $e) {
            $this->formIntegration->events()->create([
                'status' => FormIntegrationsEvent::STATUS_ERROR,
                'data' => $this->extractEventDataFromException($e),
            ]);
            Log::error('Integration failed', array_merge([
                'form_id' => $this->form->id,
                'integration_id' => $this->formIntegration->id,
            ], $this->extractEventDataFromException($e)));
        }
    }

    public function created(): void
    {
        //
    }

    /**
     * Default handle. Can be changed in child classes.
     */
    public function handle(): void
    {
        if (!$this->shouldRun()) {
            return;
        }

        Http::throw()->post($this->getWebhookUrl(), $this->getWebhookData());
    }

    abstract public static function getValidationRules(?Form $form): array;

    public static function isOAuthRequired(): bool
    {
        return false;
    }

    public static function getValidationAttributes(): array
    {
        return [];
    }

    public static function formatWebhookData(Form $form, array $submissionData): array
    {
        $formatter = (new FormSubmissionFormatter($form, $submissionData))
            ->useSignedUrlForFiles()
            ->showHiddenFields();

        // Old format - kept for retro-compatibility
        $oldFormatData = [];
        $formattedData = [];
        $fieldsWithValue = $formatter->getFieldsWithValue();

        foreach ($fieldsWithValue as $field) {
            $oldFormatData[$field['name']] = $field['value'];
            // New format using ID
            $formattedData[$field['id']] = [
                'value' => $field['value'],
                'name' => $field['name'],
                'type' => $field['type']
            ];
        }

        $data = [
            'form_title' => $form->title,
            'form_slug' => $form->slug,
            'submission' => $oldFormatData,
            'data' => $formattedData,
            'message' => 'Please do not use the `submission` field. It is deprecated and will be removed in the future.'
        ];
        if ($form->is_pro && $form->editable_submissions && isset($submissionData['submission_id'])) {
            $data['edit_link'] = $form->share_url . '?submission_id=' . Hashids::encode($submissionData['submission_id']);
        }

        return $data;
    }

    public function extractEventDataFromException(\Exception $e): array
    {
        if ($e instanceof RequestException) {
            return [
                'message' => $e->getMessage(),
                'response' => $e->response->json(),
                'status' => $e->response->status(),
            ];
        }
        return [
            'message' => $e->getMessage()
        ];
    }

    /**
     * Used in FormIntegrationRequest to format integration
     */
    public static function formatData(array $data): array
    {
        return $data;
    }
}
