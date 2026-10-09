<?php

namespace App\Modules\Acquirer\Core\Models;

use App\Modules\Acquirer\Core\Enums\IngestionStatus;
use DevApps\LaravelModulesKit\Traits\Uuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ingestion extends Model
{
    use HasFactory;
    use SoftDeletes;
    use Uuid;

    protected $fillable = [
        'acquirer_code',
        'original_filename',
        'storage_path',
        'storage_disk',
        'file_size_bytes',
        'file_extension',
        'content_hash',
        'idempotency_key',
        'status',
        'current_execution_id',
        'user_id',
        'file_metadata',
        'received_at',
    ];

    protected $casts = [
        'status' => IngestionStatus::class,
        'file_size_bytes' => 'integer',
        'file_metadata' => 'array',
        'received_at' => 'datetime',
    ];

    public function executions(): HasMany
    {
        return $this->hasMany(ProcessingExecution::class);
    }

    public function currentExecution(): HasOne
    {
        return $this->hasOne(ProcessingExecution::class, 'id', 'current_execution_id');
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
