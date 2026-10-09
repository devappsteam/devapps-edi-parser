<?php

namespace App\Modules\Acquirer\Core\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProcessingExecutionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'status' => $this->status,
            'attempt_number' => $this->attempt_number,
            'records_read' => $this->records_read,
            'records_processed' => $this->records_processed,
            'records_rejected' => $this->records_rejected,
            'warnings_count' => $this->warnings_count,
            'processing_time_ms' => $this->processing_time_ms,
            'error_message' => $this->error_message,
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
        ];
    }
}
