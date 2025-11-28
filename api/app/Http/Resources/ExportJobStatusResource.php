<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExportJobStatusResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'job_id' => $this->resource['job_id'] ?? null,
            'status' => $this->resource['status'],
            'progress' => $this->resource['progress'],
            'processed_submissions' => $this->resource['processed_submissions'] ?? null,
            'total_submissions' => $this->resource['total_submissions'] ?? null,
            'file_url' => $this->resource['file_url'] ?? null,
            'error_message' => $this->resource['error_message'] ?? null,
            'expires_at' => $this->resource['expires_at'] ?? null,
            'created_at' => $this->resource['created_at'] ?? null,
            'updated_at' => $this->resource['updated_at'] ?? null,
        ];
    }
}
