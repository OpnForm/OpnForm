<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class FormIntegrationsEventResource extends JsonResource
{
    protected static ?Collection $integrationEvents = null;

    public static function collection($resource)
    {
        self::$integrationEvents = collect($resource);

        return parent::collection($resource);
    }

    public static function withIntegrationEvents(Collection $events): void
    {
        self::$integrationEvents = $events;
    }

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'date' => date('Y-m-d H:i', strtotime($this->created_at)),
            'status' => ucfirst($this->status),
            'data' => $this->data,
            'can_retry' => $this->resource->canRetry(self::$integrationEvents),
        ];
    }
}
