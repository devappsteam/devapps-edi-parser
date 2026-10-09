<?php

namespace App\Modules\Acquirer\Core\Repositories\Contracts;

use DevApps\LaravelModulesKit\Contracts\RepositoryInterface;
use App\Modules\Acquirer\Core\Models\Ingestion;

interface IngestionRepositoryInterface extends RepositoryInterface
{
    /**
     * Find an ingestion by its acquirer code and file content hash.
     * Used for idempotency checks.
     */
    public function findByHash(string $acquirerCode, string $hash): ?Ingestion;
}
