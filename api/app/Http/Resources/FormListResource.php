<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight resource for form list endpoints.
 * Excludes heavy fields like `properties` and `removed_properties` to reduce response size.
 */
class FormListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'visibility' => $this->visibility,
            'tags' => $this->tags,
            'views_count' => $this->views_count,
            'submissions_count' => $this->submissions_count,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'last_edited_human' => $this->updated_at?->diffForHumans(),
            'closes_at' => $this->closes_at,
            'is_closed' => $this->is_closed,
            'max_submissions_count' => $this->max_submissions_count,
            'max_number_of_submissions_reached' => $this->max_number_of_submissions_reached,
            'is_pro' => $this->workspace->is_pro ?? false,
            'workspace_id' => $this->workspace_id,
            'share_url' => $this->share_url,
        ];
    }
}
