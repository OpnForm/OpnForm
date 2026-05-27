<?php

namespace App\Http\Requests\Pdf;

use App\Models\Forms\Form;
use App\Rules\PdfZoneMappingsRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePdfTemplateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Form $form */
        $form = $this->route('form');

        return [
            'name' => 'sometimes|string|max:255',
            'zone_mappings' => ['sometimes', 'array', new PdfZoneMappingsRule($form)],
            'filename_pattern' => 'sometimes|string',
            'remove_branding' => 'sometimes|boolean',
            'page_count' => 'sometimes|integer|min:1',
            'page_manifest' => 'sometimes|array|min:1',
            'page_manifest.*.id' => 'required_with:page_manifest|string',
            'page_manifest.*.type' => 'required_with:page_manifest|in:source,blank',
            'page_manifest.*.source_page' => 'nullable|integer|min:1',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $manifest = $this->input('page_manifest');
            if (!is_array($manifest)) {
                return;
            }

            $manifestIds = [];
            foreach ($manifest as $index => $entry) {
                $type = $entry['type'] ?? null;
                $sourcePage = $entry['source_page'] ?? null;
                $pageId = isset($entry['id']) ? (string) $entry['id'] : '';

                if ($pageId === '') {
                    $validator->errors()->add("page_manifest.$index.id", 'id is required for page manifest entries.');
                } elseif (in_array($pageId, $manifestIds, true)) {
                    $validator->errors()->add("page_manifest.$index.id", 'page manifest ids must be unique.');
                } else {
                    $manifestIds[] = $pageId;
                }

                if ($type === 'source' && !is_int($sourcePage)) {
                    $validator->errors()->add("page_manifest.$index.source_page", 'source_page is required for source pages.');
                }
                if ($type === 'blank' && $sourcePage !== null) {
                    $validator->errors()->add("page_manifest.$index.source_page", 'source_page must be null for blank pages.');
                }
            }

            $zones = $this->input('zone_mappings');
            if (is_array($zones) && !empty($manifestIds)) {
                foreach ($zones as $zoneIndex => $zone) {
                    if (!is_array($zone)) {
                        continue;
                    }
                    $zonePageId = isset($zone['page_id']) ? (string) $zone['page_id'] : '';
                    if ($zonePageId !== '' && !in_array($zonePageId, $manifestIds, true)) {
                        $validator->errors()->add("zone_mappings.$zoneIndex.page_id", 'zone page_id must exist in page_manifest.');
                    }
                }
            }
        });
    }
}
