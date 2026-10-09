<?php

use App\Modules\Acquirer\Core\Services\IngestionService;
use App\Modules\Acquirer\Core\Services\ProcessingService;
use Illuminate\Http\UploadedFile;

$filePath = __DIR__ . '/Arquivos de Teste/Alelo/20260907_0348_NAIP_04_0002096404_95421830000107_0021_PRD_0001040.txt';
$file = new UploadedFile($filePath, 'alelo_test.txt', 'text/plain', null, true);

echo "1. Uploading file...\n";
$ingestionService = app(IngestionService::class);
$ingestion = $ingestionService->ingest('ALELO', $file);
echo "   Ingestion UUID: " . $ingestion->uuid . "\n";
echo "   Status: " . $ingestion->status->value . "\n";

echo "2. Processing file...\n";
$processingService = app(ProcessingService::class);

$execution = $ingestion->executions()->create([
    'status' => 'queued', 
    'attempt_number' => 1
]);
$ingestion->update(['current_execution_id' => $execution->id]);

$processingService->process($ingestion, $execution);

// Refresh to get latest DB stats
$execution->refresh();

echo "\n--- EXECUTION REPORT ---\n";
echo "Total Records Read:      " . $execution->records_read . "\n";
echo "Total Records Processed: " . $execution->records_processed . "\n";
echo "Total Records Rejected:  " . $execution->records_rejected . "\n";
echo "Warnings/Errors Found:   " . $execution->warnings_count . "\n";
echo "Processing Time:         " . $execution->processing_time_ms . " ms\n";
echo "Final Status:            " . $execution->status . "\n";
