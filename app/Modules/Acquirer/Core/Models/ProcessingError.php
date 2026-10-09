<?php

namespace App\Modules\Acquirer\Core\Models;

use App\Modules\Acquirer\Core\Enums\ErrorSeverity;
use DevApps\LaravelModulesKit\Traits\Uuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessingError extends Model
{
    use HasFactory;
    use Uuid;

    protected $fillable = [
        'ingestion_id',
        'processing_execution_id',
        'line_number',
        'record_type',
        'field_name',
        'error_code',
        'message',
        'severity',
        'sanitized_value',
    ];

    protected $casts = [
        'line_number' => 'integer',
        'severity' => ErrorSeverity::class,
    ];

    public function ingestion(): BelongsTo
    {
        return $this->belongsTo(Ingestion::class);
    }

    public function execution(): BelongsTo
    {
        return $this->belongsTo(ProcessingExecution::class, 'processing_execution_id');
    }
}
