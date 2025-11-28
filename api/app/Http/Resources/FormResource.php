<?php

namespace App\Http\Resources;

use App\Http\Middleware\Form\ProtectedForm;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use App\Models\User as UserModel;

class FormResource extends JsonResource
{
    private array $cleanings = [];

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        if (!$this->userIsFormOwner() && ProtectedForm::isProtected($request, $this->resource)) {
            return $this->getProtectedForm();
        }

        // Owner/Member view: readonly members can see everything except password
        $ownerData = [];
        if ($this->userIsFormOwner()) {
            $ownerData = [
                'views_count' => $this->views_count,
                'submissions_count' => $this->submissions_count,
                'redirect_url' => $this->redirect_url,
                'submissions_url' => $this->submissions_url,
                'database_fields_update' => $this->database_fields_update,
                'clear_empty_fields_on_update' => $this->clear_empty_fields_on_update,
                'cleanings' => $this->getCleanigns(),
                'can_be_indexed' => $this->can_be_indexed,
                'password' => $this->password,
                'tags' => $this->tags,
                'visibility' => $this->visibility,
                'removed_properties' => $this->removed_properties,
                'last_edited_human' => $this->updated_at?->diffForHumans(),
                'seo_meta' => $this->seo_meta,
            ];

            if ($this->userIsReadonly()) {
                unset($ownerData['password']);
            }
        }

        return array_merge(parent::toArray($request), $ownerData, [
            'settings' => $this->settings ?? new \stdClass(),
            'is_pro' => $this->workspaceIsPro(),
            'is_trialing' => $this->workspaceIsTrialing(),
            'workspace_id' => $this->workspace_id,
            'workspace' => $this->userIsFormOwner()
                ? new WorkspaceResource($this->workspace)
                : (new WorkspaceResource($this->workspace))->restrictForGuest(),
            'is_closed' => $this->is_closed,
            'size' => $this->size,
            'is_password_protected' => false,
            'has_password' => $this->has_password,
            'max_number_of_submissions_reached' => $this->max_number_of_submissions_reached,
            'form_pending_submission_key' => $this->form_pending_submission_key,
            'max_file_size' => $this->workspace->max_file_size / 1000000,
            'auto_save' => $this->getAutoSave(),
            'presentation_style' => $this->presentation_style ?? 'classic',
            'cover_settings' => $this->cover_settings ?? new \stdClass(),
            'translations' => $this->translations ?? new \stdClass(),
        ]);
    }

    public function setCleanings(array $cleanings)
    {
        $this->cleanings = $cleanings;

        return $this;
    }

    private function getProtectedForm()
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'slug' => $this->slug,
            'custom_code' => $this->custom_code,
            'custom_css' => $this->custom_css,
            'dark_mode' => $this->dark_mode,
            'transparent_background' => $this->transparent_background,
            'color' => $this->color,
            'language' => $this->language,
            'theme' => $this->theme,
            'is_password_protected' => true,
            'presentation_style' => $this->presentation_style,
            'has_password' => $this->has_password,
            'width' => 'centered',
            'layout_rtl' => $this->layout_rtl,
            'no_branding' => $this->no_branding,
            'properties' => [],
            'logo_picture' => $this->logo_picture,
            'seo_meta' => $this->seo_meta,
            'cover_picture' => $this->cover_picture,
            'cover_settings' => $this->cover_settings ?? new \stdClass(),
        ];
    }

    private function workspaceIsPro()
    {
        return $this->workspace->is_pro ?? $this->is_pro;
    }

    private function workspaceIsTrialing()
    {
        return $this->workspace->is_trialing;
    }

    private function userIsFormOwner()
    {
        if (!Auth::check()) {
            return false;
        }

        /** @var UserModel|null $user */
        $user = Auth::user();

        // Use preloaded workspace users if available to avoid N+1 queries
        if ($this->relationLoaded('workspace') && $this->workspace->relationLoaded('users')) {
            return $this->workspace->users->contains('id', $user->id);
        }

        // Fallback to checking ownership
        return $user instanceof UserModel && $user->ownsForm($this->resource);
    }

    private function userIsReadonly(): bool
    {
        if (!Auth::check()) {
            return false;
        }

        /** @var UserModel|null $user */
        $user = Auth::user();

        // Use preloaded relationships to avoid N+1 when available
        if ($this->relationLoaded('workspace') && $this->workspace->relationLoaded('users')) {
            $pivot = $this->workspace->users->firstWhere('id', $user->id)?->pivot;
            if ($pivot && isset($pivot->role)) {
                return $pivot->role === \App\Models\User::ROLE_READONLY;
            }
        }

        // Minimal query fallback targeting only current user pivot
        return $this->workspace
            ->users()
            ->wherePivot('user_id', $user->id)
            ->wherePivot('role', \App\Models\User::ROLE_READONLY)
            ->exists();
    }

    private function getCleanigns()
    {
        return $this->cleanings;
    }

    private function hasPaymentBlock()
    {
        return array_filter($this->properties, function ($property) {
            return $property['type'] === 'payment';
        });
    }

    private function getAutoSave()
    {
        return $this->hasPaymentBlock() ? true : $this->auto_save;
    }
}
