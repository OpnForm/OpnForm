<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;
use Stevebauman\Purify\Facades\Purify;
use App\Service\Forms\SubmissionUrlService;

/**
 * @property array $data
 */
class FormSubmissionResource extends JsonResource
{
    public bool $publiclyAccessed = false;

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        // Single-pass transform: files/signatures → signed URLs, rich_text → purified HTML
        $this->transformDataByFieldTypes();

        if (!$this->publiclyAccessed) {
            $this->addExtraData();
        }

        return array_merge([
            'data' => $this->data,
            'completion_time' => $this->completion_time,
        ], ($this->publiclyAccessed) ? [] : [
            'form_id' => $this->form_id,
            'id' => $this->id,
            'submission_id' => SubmissionUrlService::getSubmissionIdentifier($this->resource),
        ]);
    }

    public function publiclyAccessed($publiclyAccessed = true)
    {
        $this->publiclyAccessed = $publiclyAccessed;
        return $this;
    }

    private function addExtraData()
    {
        $extraData = [
            'status' => $this->status,
            'created_at' => $this->created_at->toDateTimeString(),
            'id' => $this->id,
            'submission_id' => SubmissionUrlService::getSubmissionIdentifier($this->resource)
        ];
        if ($this->form->enable_ip_tracking && $this->form->is_pro && !empty($this->meta)) {
            $extraData['ip_address'] = $this->meta['ip_address'] ?? null;
        }

        $this->data = array_merge($this->data, $extraData);
    }

    /**
     * Transform data based on field types in a single pass.
     */
    private function transformDataByFieldTypes(): void
    {
        $data = $this->data;
        $formFields = collect($this->form->properties)->concat(collect($this->form->removed_properties));

        foreach ($formFields as $field) {
            $fieldId = $field['id'] ?? null;
            $type = $field['type'] ?? null;
            if (!$fieldId || !array_key_exists($fieldId, $data)) {
                continue;
            }

            $value = $data[$fieldId];

            // Files and signatures → signed URLs array
            if (in_array($type, ['files', 'signature'], true) && !empty($value)) {
                $fileItems = is_array($value) ? $value : [$value];
                $mapped = collect($fileItems)
                    ->filter(fn ($file) => !is_null($file) && $file !== '')
                    ->map(function ($file) {
                        return [
                            'file_url' => URL::signedRoute(
                                'open.forms.submissions.file',
                                [$this->form_id, $file],
                                now()->addMinutes(10)
                            ),
                            'file_name' => $file,
                        ];
                    });
                $data[$fieldId] = $mapped;
                continue;
            }

            // Rich text → purified
            if ($type === 'rich_text' && is_string($value) && $value !== '') {
                $data[$fieldId] = Purify::clean($value);
            }
        }

        $this->data = $data;
    }
}
