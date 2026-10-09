<?php

namespace App\Modules\Acquirer\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Acquirer\Core\Http\Requests\UploadIngestionRequest;
use App\Modules\Acquirer\Core\Http\Resources\IngestionResource;
use App\Modules\Acquirer\Core\Jobs\ProcessIngestionJob;
use App\Modules\Acquirer\Core\Repositories\Contracts\IngestionRepositoryInterface;
use App\Modules\Acquirer\Core\Services\IngestionService;
use Illuminate\Http\JsonResponse;

class IngestionController extends Controller
{
    public function __construct(
        protected IngestionService $ingestionService,
        protected IngestionRepositoryInterface $repository
    ) {}

    /**
     * Upload and ingest a new acquirer file.
     * Starts the async extraction process.
     */
    public function upload(UploadIngestionRequest $request): JsonResponse
    {
        try {
            $ingestion = $this->ingestionService->ingest(
                acquirerCode: $request->input('acquirer'),
                file: $request->file('file'),
                idempotencyKey: $request->input('idempotency_key'),
                userId: $request->user()?->id // If auth is configured
            );

            // If it's a new ingestion or it needs reprocessing, dispatch job
            if ($ingestion->wasRecentlyCreated || $ingestion->status->allowsReprocessing()) {
                ProcessIngestionJob::dispatch($ingestion);
            }

            $ingestion->load('currentExecution');

            return response()->json([
                'message' => $ingestion->wasRecentlyCreated ? 'File ingested successfully' : 'Idempotent ingestion hit',
                'data' => new IngestionResource($ingestion),
            ], $ingestion->wasRecentlyCreated ? 201 : 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Ingestion failed',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get the status of an ingestion.
     */
    public function show(string $uuid): IngestionResource
    {
        $ingestion = $this->repository->findByUuidOrFail($uuid);
        $ingestion->load('currentExecution');

        return new IngestionResource($ingestion);
    }

    /**
     * Retrieve the parsed canonical records for an ingestion.
     */
    public function records(string $uuid): \Illuminate\Http\JsonResponse
    {
        $ingestion = $this->repository->findByUuidOrFail($uuid);

        $records = $ingestion->canonicalRecords()
            ->orderBy('source_line_number', 'asc')
            ->paginate(config('modules.core.pagination.per_page', 50));

        return response()->json($records);
    }
}
