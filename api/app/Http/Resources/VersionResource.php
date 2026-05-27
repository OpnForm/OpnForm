<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class VersionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->version_id,
            'created_at' => $this->created_at,
            'user' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'photo_url' => $this->user->photo_url,
            ] : null,
            'diff' => $this->diff()  // Computed differences
        ];
    }
}
