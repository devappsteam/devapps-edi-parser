<?php

namespace App\Modules\Acquirer\Core\Models;

use DevApps\LaravelModulesKit\Traits\Uuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcessingExecution extends Model
{
    use HasFactory;
    use Uuid;

    protected $fillable = [
        'ingestion_id',
        'status',
        'attempt_number',
        'records_read',
        'records_processed',
        'records_rejected',
        'warnings_count',
        'header_data',
        'trailer_total_records',
        'trailer_total_branches',
        'error_message',
        'processing_time_ms',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'attempt_number' => 'integer',
        'records_read' => 'integer',
        'records_processed' => 'integer',
        'records_rejected' => 'integer',
        'warnings_count' => 'integer',
        'trailer_total_records' => 'integer',
        'trailer_total_branches' => 'integer',
        'header_data' => 'array',
        'processing_time_ms' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function ingestion(): BelongsTo
    {
        return $this->belongsTo(Ingestion::class);
    }

    public function canonicalRecords(): HasMany
    {
        return $this->hasMany(CanonicalRecord::class);
    }

    public function processingErrors(): HasMany
    {
        return $this->hasMany(ProcessingError::class);
    }
}
