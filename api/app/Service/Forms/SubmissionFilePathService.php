<?php

namespace App\Service\Forms;

use App\Models\Forms\Form;
use App\Service\Storage\FileUploadPathService;

class SubmissionFilePathService
{
    public function fromData(Form $form, array $data): array
    {
        $fileFieldIds = collect($form->properties)
            ->concat(collect($form->removed_properties))
            ->filter(function ($property) {
                $type = $property['type'] ?? null;

                return in_array($type, ['files', 'signature'], true)
                    || ($type === 'url' && ($property['file_upload'] ?? false));
            })
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->all();

        return collect($data)
            ->filter(fn ($value, $key) => $value && in_array((string) $key, $fileFieldIds, true))
            ->flatMap(fn ($value) => is_array($value) ? $value : [$value])
            ->filter(fn ($fileName) => is_string($fileName) && $fileName !== '')
            ->map(fn (string $fileName) => FileUploadPathService::getFileUploadPath(
                $form->id,
                urldecode($fileName)
            ))
            ->unique()
            ->values()
            ->all();
    }
}
