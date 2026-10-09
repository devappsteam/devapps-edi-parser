<?php

namespace App\Modules\Acquirer\Core\Services;

use App\Modules\Acquirer\Core\Enums\IngestionStatus;
use App\Modules\Acquirer\Core\Models\CanonicalRecord;
use App\Modules\Acquirer\Core\Models\Ingestion;
use App\Modules\Acquirer\Core\Models\ProcessingError;
use App\Modules\Acquirer\Core\Models\ProcessingExecution;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ProcessingService
{
    public function __construct(
        protected ParserRegistry $parserRegistry
    ) {}

    /**
     * Executes the ETL process for an ingestion using stream processing.
     */
    public function process(Ingestion $ingestion, ProcessingExecution $execution): void
    {
        $startTime = microtime(true);
        
        try {
            $this->markAsProcessing($ingestion, $execution);
            
            $parser = $this->parserRegistry->resolve($ingestion->acquirer_code);
            $stream = $this->openFileStream($ingestion);

            $batchSize = config('modules.core.processing.batch_size', 100);
            $canonicalBatch = [];
            $errorBatch = [];

            // Tracking stats inside execution object
            $execution->records_read = 0;
            $execution->records_processed = 0;
            $execution->records_rejected = 0;
            $execution->warnings_count = 0;

            // Generator yields ParsedRecord DTOs iteratively (Streaming)
            foreach ($parser->parse($stream, $ingestion) as $parsedRecord) {
                $execution->records_read++;

                // Collect errors/warnings
                if (!empty($parsedRecord->errors)) {
                    foreach ($parsedRecord->errors as $error) {
                        $errorBatch[] = [
                            'uuid' => \Illuminate\Support\Str::uuid()->toString(),
                            'ingestion_id' => $ingestion->id,
                            'processing_execution_id' => $execution->id,
                            'line_number' => $parsedRecord->lineNumber,
                            'record_type' => $parsedRecord->recordType,
                            'field_name' => $error->field,
                            'error_code' => $error->code,
                            'message' => $error->message,
                            'severity' => $error->severity->value,
                            'sanitized_value' => $error->sanitizedValue,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                        
                        if ($error->severity->value === 'warning') {
                            $execution->warnings_count++;
                        }
                    }
                }

                // Header logic
                if ($parsedRecord->isHeader) {
                    $execution->header_data = $parsedRecord->fields;
                    // We don't save the header as a financial canonical record
                    continue;
                }

                // Trailer logic
                if ($parsedRecord->isTrailer) {
                    $execution->trailer_total_records = $parsedRecord->field('total_records');
                    $execution->trailer_total_branches = $parsedRecord->field('total_branches');
                    continue;
                }

                // Reject if blocking errors exist
                if ($parsedRecord->hasBlockingErrors()) {
                    $execution->records_rejected++;
                    continue;
                }

                // Valid canonical record mapping
                $canonicalBatch[] = $this->mapToCanonicalArray($ingestion, $execution, $parsedRecord);
                $execution->records_processed++;

                // Flush batches to avoid memory growth
                if (count($canonicalBatch) >= $batchSize) {
                    $this->flushBatches($canonicalBatch, $errorBatch);
                }
            }

            // Flush remaining
            $this->flushBatches($canonicalBatch, $errorBatch);
            
            fclose($stream);

            $this->markAsCompleted($ingestion, $execution, $startTime);

        } catch (\Throwable $e) {
            $this->markAsFailed($ingestion, $execution, $e, $startTime);
            throw $e; // Rethrow to let the queue logic handle retries if applicable
        }
    }

    protected function openFileStream(Ingestion $ingestion)
    {
        $disk = Storage::disk($ingestion->storage_disk);
        if (!$disk->exists($ingestion->storage_path)) {
            throw new RuntimeException("File not found on disk: {$ingestion->storage_path}");
        }

        $stream = $disk->readStream($ingestion->storage_path);
        if (!$stream) {
            throw new RuntimeException("Could not open stream for file: {$ingestion->storage_path}");
        }

        return $stream;
    }

    protected function flushBatches(array &$canonicalBatch, array &$errorBatch): void
    {
        DB::transaction(function () use (&$canonicalBatch, &$errorBatch) {
            if (!empty($canonicalBatch)) {
                CanonicalRecord::insert($canonicalBatch);
                $canonicalBatch = [];
            }
            if (!empty($errorBatch)) {
                ProcessingError::insert($errorBatch);
                $errorBatch = [];
            }
        });
    }

    protected function mapToCanonicalArray(Ingestion $ingestion, ProcessingExecution $execution, \App\Modules\Acquirer\Core\DTOs\ParsedRecord $record): array
    {
        $now = now();
        
        return [
            'uuid' => \Illuminate\Support\Str::uuid()->toString(),
            'ingestion_id' => $ingestion->id,
            'processing_execution_id' => $execution->id,
            'acquirer_code' => $ingestion->acquirer_code,
            'source_record_type' => $record->recordType,
            'source_line_number' => $record->lineNumber,
            
            'payment_key' => $record->field('payment_key'),
            'unique_movement_id' => $record->field('unique_movement_id'),
            'unified_payment_id' => $record->field('unified_payment_id'),
            'compensation_id' => $record->field('compensation_id'),
            'authorization_code' => $record->field('authorization_code'),
            'nsu_doc' => $record->field('nsu_doc'),
            
            'transaction_date' => $record->field('transaction_date'),
            'capture_time' => $record->field('capture_time'),
            'processing_date' => $record->field('processing_date'),
            'expected_processing_date' => $record->field('expected_processing_date'),
            'expected_payment_date' => $record->field('expected_payment_date'),
            'payment_date' => $record->field('payment_date'),
            'original_payment_date' => $record->field('original_payment_date'),
            'bank_sent_date' => $record->field('bank_sent_date'),
            'status_update_date' => $record->field('status_update_date'),
            
            'movement_type_code' => $record->field('movement_type_code'),
            'product_code' => $record->field('product_code'),
            'brand_code' => $record->field('brand_code'),
            
            'establishment_id' => $record->field('establishment_id'),
            'branch_id' => $record->field('branch_id'),
            'payment_matrix_id' => $record->field('payment_matrix_id'),
            'cnpj_root' => $record->field('cnpj_root'),
            
            'gross_amount' => $record->field('gross_amount'),
            'net_amount' => $record->field('net_amount'),
            'fee_amount' => $record->field('fee_amount'),
            'fee_rate' => $record->field('fee_rate'),
            'tariff_amount' => $record->field('tariff_amount'),
            'payment_amount' => $record->field('payment_amount'),
            
            'payment_type' => $record->field('payment_type'),
            'payment_status_code' => $record->field('payment_status_code'),
            'rejection_code' => $record->field('rejection_code'),
            'is_resend' => $record->field('is_resend', false),
            
            'bank_code' => $record->field('bank_code'),
            'agency_code' => $record->field('agency_code'),
            'account_number' => $record->field('account_number'),
            
            // Collect any non-canonical properties into metadata
            'metadata' => json_encode($record->field('metadata', [])),
            'raw_data' => $record->rawData,
            
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    protected function markAsProcessing(Ingestion $ingestion, ProcessingExecution $execution): void
    {
        $execution->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);

        $ingestion->update(['status' => IngestionStatus::PROCESSING]);
    }

    protected function markAsCompleted(Ingestion $ingestion, ProcessingExecution $execution, float $startTime): void
    {
        $execution->update([
            'status' => 'completed',
            'completed_at' => now(),
            'processing_time_ms' => (int) ((microtime(true) - $startTime) * 1000),
        ]);

        $finalStatus = $execution->warnings_count > 0 
            ? IngestionStatus::COMPLETED_WITH_WARNINGS 
            : IngestionStatus::COMPLETED;

        $ingestion->update(['status' => $finalStatus]);
    }

    protected function markAsFailed(Ingestion $ingestion, ProcessingExecution $execution, \Throwable $e, float $startTime): void
    {
        Log::error("Ingestion processing failed: " . $e->getMessage(), [
            'ingestion_id' => $ingestion->id,
            'execution_id' => $execution->id,
            'trace' => $e->getTraceAsString(),
        ]);

        $execution->update([
            'status' => 'failed',
            'error_message' => $e->getMessage(),
            'completed_at' => now(),
            'processing_time_ms' => (int) ((microtime(true) - $startTime) * 1000),
        ]);

        $ingestion->update(['status' => IngestionStatus::FAILED]);
    }
}
