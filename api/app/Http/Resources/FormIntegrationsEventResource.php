<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class FormIntegrationsEventResource extends JsonResource
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
            'date' => date('Y-m-d H:i', strtotime($this->created_at)),
            'status' => ucfirst($this->status),
            'tracking_id' => $this->tracking_id,
            'email_tracking' => $this->tracking_id !== null,
            'legacy_email' => !$this->tracking_id && $this->integration?->integration_id === 'email',
            'updated_at' => $this->updated_at?->toIso8601String(),
            'data' => $this->data
        ];
    }
}
