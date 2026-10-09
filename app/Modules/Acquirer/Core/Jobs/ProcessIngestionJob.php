<?php

namespace App\Modules\Acquirer\Core\Jobs;

use App\Modules\Acquirer\Core\Enums\IngestionStatus;
use App\Modules\Acquirer\Core\Models\Ingestion;
use App\Modules\Acquirer\Core\Services\ProcessingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcessIngestionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Delete the job if its models no longer exist.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * How many times the job may be attempted.
     */
    public int $tries;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff;

    public function __construct(
        public Ingestion $ingestion
    ) {
        $this->tries = config('modules.core.processing.max_attempts', 3);
        $this->backoff = config('modules.core.processing.retry_backoff', 60);
        $this->onQueue(config('modules.core.processing.queue', 'default'));
    }

    public function handle(ProcessingService $processingService): void
    {
        // Don't reprocess if already successfully completed
        if ($this->ingestion->status->isTerminal() && !$this->ingestion->status->allowsReprocessing()) {
            return;
        }

        // Create a new execution attempt
        $execution = DB::transaction(function () {
            // Find the last attempt number
            $lastAttempt = $this->ingestion->executions()->max('attempt_number') ?? 0;

            $execution = $this->ingestion->executions()->create([
                'status' => 'queued',
                'attempt_number' => $lastAttempt + 1,
            ]);

            $this->ingestion->update([
                'current_execution_id' => $execution->id,
                'status' => IngestionStatus::QUEUED,
            ]);

            return $execution;
        });

        // The processing service handles status updates (Processing, Completed, Failed)
        $processingService->process($this->ingestion, $execution);
    }
}
