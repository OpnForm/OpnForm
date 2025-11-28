<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class WorkspaceResource extends JsonResource
{
    public static $wrap = null;

    /**
     * When true, only expose minimal public fields (for guests/non-members).
     */
    private bool $restrictForGuest = false;

    /**
     * Enable guest-restricted output for this resource instance.
     */
    public function restrictForGuest(): self
    {
        $this->restrictForGuest = true;

        return $this;
    }

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        if ($this->restrictForGuest) {
            // Minimal public shape: avoid leaking workspace details
            return [
                'id' => $this->resource->id,
                'max_file_size' => $this->resource->max_file_size / 1000000,
            ];
        }

        return array_merge(parent::toArray($request), [
            'max_file_size' => $this->max_file_size / 1000000,
            'is_readonly' => $this->isReadonlyUser($request->user()),
            'is_admin' => $this->isAdminUser($request->user()),
            'users_count' => $this->users_count,
        ]);
    }
}
