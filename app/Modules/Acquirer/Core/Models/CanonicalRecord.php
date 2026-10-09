<?php

namespace App\Modules\Acquirer\Core\Models;

use DevApps\LaravelModulesKit\Traits\Uuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CanonicalRecord extends Model
{
    use HasFactory;
    use Uuid;

    protected $fillable = [
        'ingestion_id',
        'processing_execution_id',
        'acquirer_code',
        'source_record_type',
        'source_line_number',
        'payment_key',
        'unique_movement_id',
        'unified_payment_id',
        'compensation_id',
        'authorization_code',
        'nsu_doc',
        'transaction_date',
        'capture_time',
        'processing_date',
        'expected_processing_date',
        'expected_payment_date',
        'payment_date',
        'original_payment_date',
        'bank_sent_date',
        'status_update_date',
        'movement_type_code',
        'product_code',
        'brand_code',
        'establishment_id',
        'branch_id',
        'payment_matrix_id',
        'cnpj_root',
        'gross_amount',
        'net_amount',
        'fee_amount',
        'fee_rate',
        'tariff_amount',
        'payment_amount',
        'payment_type',
        'payment_status_code',
        'rejection_code',
        'is_resend',
        'bank_code',
        'agency_code',
        'account_number',
        'metadata',
        'raw_data',
    ];

    protected $casts = [
        'source_line_number' => 'integer',
        'transaction_date' => 'date',
        'processing_date' => 'date',
        'expected_processing_date' => 'date',
        'expected_payment_date' => 'date',
        'payment_date' => 'date',
        'original_payment_date' => 'date',
        'bank_sent_date' => 'date',
        'status_update_date' => 'date',
        'gross_amount' => 'decimal:6',
        'net_amount' => 'decimal:6',
        'fee_amount' => 'decimal:6',
        'fee_rate' => 'decimal:6',
        'tariff_amount' => 'decimal:6',
        'payment_amount' => 'decimal:6',
        'is_resend' => 'boolean',
        'metadata' => 'array',
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
