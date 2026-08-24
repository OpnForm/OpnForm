<?php

namespace App\Service\Forms;

use App\Models\Forms\Form;
use Illuminate\Support\Str;

final class FormUpdateService
{
    /**
     * @return array{form: Form, cleanings: array, cleaning_keys: array, has_cleaned: bool}
     */
    public function update(Form $form, array $data): array
    {
        $cleaner = (new FormCleaner())
            ->processData($data, $form)
            ->simulateCleaning($form->workspace);
        $formData = $cleaner->getData();
        unset($formData['schema_version']);

        $newPropertyIds = collect($formData['properties'])->pluck('id')->flip()->all();
        $formData['removed_properties'] = array_merge(
            $form->removed_properties ?? [],
            collect($form->properties)
                ->filter(fn (array $field): bool => ! Str::of($field['type'])->startsWith('nf-') && ! isset($newPropertyIds[$field['id']]))
                ->all(),
        );

        $form->slug = config('app.self_hosted') && ! empty($formData['slug'])
            ? $formData['slug']
            : $form->slug;
        $form->update($formData);

        return [
            'form' => $form,
            'cleanings' => $cleaner->getPerformedCleanings(),
            'cleaning_keys' => $cleaner->getCleaningKeys(),
            'has_cleaned' => $cleaner->hasCleaned(),
        ];
    }
}
