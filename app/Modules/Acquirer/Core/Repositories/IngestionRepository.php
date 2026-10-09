<?php

namespace App\Modules\Acquirer\Core\Repositories;

use App\Modules\Acquirer\Core\Models\Ingestion;
use App\Modules\Acquirer\Core\Repositories\Contracts\IngestionRepositoryInterface;
use DevApps\LaravelModulesKit\Support\BaseRepository;

class IngestionRepository extends BaseRepository implements IngestionRepositoryInterface
{
    public function __construct(Ingestion $model)
    {
        parent::__construct($model);
    }

    public function findByHash(string $acquirerCode, string $hash): ?Ingestion
    {
        return $this->model->newQuery()
            ->where('acquirer_code', $acquirerCode)
            ->where('content_hash', $hash)
            ->first();
    }
}
