<?php

namespace App\Service\Forms;

use App\Models\Forms\Form;
use App\Models\User;
use App\Models\Workspace;

class FormCreationService
{
    /**
     * @return array{form: Form, cleanings: array, has_cleaned: bool}
     */
    public function create(array $data, User $creator, Workspace $workspace): array
    {
        $cleaner = new FormCleaner();
        $formData = $cleaner
            ->processData($data)
            ->simulateCleaning($workspace)
            ->getData();

        unset($formData['schema_version']);
        $formData['workspace_id'] = $workspace->id;

        $form = Form::create(array_merge($formData, [
            'creator_id' => $creator->id,
        ]));

        if (config('app.self_hosted') && ! empty($formData['slug'])) {
            $form->slug = $formData['slug'];
            $form->save();
        }

        return [
            'form' => $form,
            'cleanings' => $cleaner->getPerformedCleanings(),
            'has_cleaned' => $cleaner->hasCleaned(),
        ];
    }
}
