<?php

namespace App\Service\Admin;

use App\Models\Forms\Form;
use App\Models\User;

class AdminForms
{
    use AdminResponses;
    public function getDeletedForms(User $user)
    {
        $deletedForms = $user->forms()->with('creator')->onlyTrashed()->get()->map(function ($form) {
            return  [
                "id" => $form->id,
                "slug" => $form->slug,
                "title" => $form->title,
                "created_by" => $form->creator->email,
                "deleted_at" => $form->deleted_at->format('Y-m-d'),
            ];
        });
        return $this->success(['forms' =>  $deletedForms]);
    }

    public function restoreDeletedForm(string $slug)
    {
        $form = Form::onlyTrashed()->whereSlug($slug)->firstOrFail();
        $form->restore();

        AdminOperations::log('Restore deleted form', [
            'form_id' => $form->id,
        ]);

        return  $this->success(['message' => 'Form restored successfully']);
    }
}
