<?php

namespace App\Modules\Acquirer\Core\Services;

use App\Modules\Acquirer\Core\Enums\IngestionStatus;
use App\Modules\Acquirer\Core\Models\Ingestion;
use App\Modules\Acquirer\Core\Repositories\Contracts\IngestionRepositoryInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class IngestionService
{
    public function __construct(
        protected IngestionRepositoryInterface $repository,
        protected ParserRegistry $parserRegistry
    ) {}

    /**
     * Ingests a new file. Validates acquirer, calculates hash incrementally,
     * checks idempotency, and persists the ingestion record.
     */
    public function ingest(string $acquirerCode, UploadedFile $file, ?string $idempotencyKey = null, ?int $userId = null): Ingestion
    {
        $acquirerCode = strtoupper($acquirerCode);

        // 1. Validate acquirer is supported
        $this->parserRegistry->resolve($acquirerCode); // throws if not supported

        // 2. Incremental SHA-256 Hash Calculation (avoids loading large files into memory)
        $hash = $this->calculateHash($file->getRealPath());

        // 3. Idempotency Check
        $existing = $this->repository->findByHash($acquirerCode, $hash);
        if ($existing) {
            // If the idempotency key was provided, check if it matches to consider it a safe retry
            if ($idempotencyKey && $existing->idempotency_key !== $idempotencyKey) {
                // Same content, different key -> potentially distinct business intent or clash
                // Depending on rules, you might reject or accept. The DB constraint prevents actual duplicates.
            }
            return $existing; // Return the existing ingestion idempotently
        }

        // 4. Store the file safely
        $disk = config('modules.core.storage.disk', 'local');
        $prefix = config('modules.core.storage.prefix', 'ingestions');
        $path = Storage::disk($disk)->putFileAs(
            $prefix . '/' . $acquirerCode . '/' . date('Y/m/d'),
            $file,
            $this->generateSafeFilename($acquirerCode, $file->getClientOriginalExtension())
        );

        if (!$path) {
            throw new RuntimeException("Failed to store uploaded file on disk [{$disk}].");
        }

        // 5. Create Ingestion Record
        return $this->repository->create([
            'acquirer_code' => $acquirerCode,
            'original_filename' => $file->getClientOriginalName(),
            'storage_path' => $path,
            'storage_disk' => $disk,
            'file_size_bytes' => $file->getSize(),
            'file_extension' => $file->getClientOriginalExtension(),
            'content_hash' => $hash,
            'idempotency_key' => $idempotencyKey,
            'status' => IngestionStatus::RECEIVED,
            'user_id' => $userId,
            'received_at' => now(),
        ]);
    }

    protected function calculateHash(string $filePath): string
    {
        $context = hash_init('sha256');
        $handle = fopen($filePath, 'rb');

        if (!$handle) {
            throw new RuntimeException("Could not open file for hashing: {$filePath}");
        }

        while (!feof($handle)) {
            $chunk = fread($handle, 8192); // 8KB chunks
            if ($chunk !== false) {
                hash_update($context, $chunk);
            }
        }

        fclose($handle);

        return hash_final($context);
    }

    protected function generateSafeFilename(string $acquirerCode, string $extension): string
    {
        $timestamp = now()->format('Ymd_His');
        $uuid = \Illuminate\Support\Str::uuid()->toString();
        $ext = $extension ? '.' . strtolower($extension) : '';

        return sprintf('%s_%s_%s%s', $acquirerCode, $timestamp, $uuid, $ext);
    }
}
