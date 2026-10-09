<?php

namespace App\Modules\User\Services;

use App\Modules\User\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

class UserService
{
    public function __construct(protected UserRepositoryInterface $repository)
    {
    }

    public function all(array $relations = []): Collection
    {
        return $this->repository->all($relations);
    }

    public function paginate(int $perPage = 15, array $relations = []): LengthAwarePaginator
    {
        return $this->repository->paginate($perPage, $relations);
    }

    public function findById(int $id, array $relations = []): ?Model
    {
        return $this->repository->findById($id, $relations);
    }

    public function findByUuid(string $uuid, array $relations = []): ?Model
    {
        return $this->repository->findByUuid($uuid, $relations);
    }

    public function create(array $data): Model
    {
        return $this->repository->create($data);
    }

    public function update(Model $model, array $data): bool
    {
        return $this->repository->update($model, $data);
    }

    public function delete(Model $model): bool
    {
        return $this->repository->delete($model);
    }

    public function restore(Model $model): bool
    {
        return $this->repository->restore($model);
    }
}
