<?php

namespace App\Modules\Acquirer\Core\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IngestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'acquirer_code' => $this->acquirer_code,
            'original_filename' => $this->original_filename,
            'file_size_bytes' => $this->file_size_bytes,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'idempotency_key' => $this->idempotency_key,
            'received_at' => $this->received_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'current_execution' => new ProcessingExecutionResource($this->whenLoaded('currentExecution')),
        ];
    }
}
