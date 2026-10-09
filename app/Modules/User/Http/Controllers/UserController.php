<?php

namespace App\Modules\User\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Http\Requests\StoreUserRequest;
use App\Modules\User\Http\Requests\UpdateUserRequest;
use App\Modules\User\Http\Resources\UserResource;
use App\Modules\User\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class UserController extends Controller
{
    public function __construct(protected UserService $service)
    {
    }

    public function index(): AnonymousResourceCollection
    {
        $users = $this->service->paginate(
            perPage: request()->integer('per_page', (int) config('modules.user.pagination.per_page', 15))
        );

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->service->create($request->validated());

        return (new UserResource($user))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $uuid): UserResource
    {
        return new UserResource($this->findOrFail($uuid));
    }

    public function update(UpdateUserRequest $request, string $uuid): UserResource
    {
        $user = $this->findOrFail($uuid);
        $updated = $this->service->update($user, $request->validated());

        if (!$updated) {
            throw new RuntimeException('Failed to update user.');
        }

        return new UserResource($user->refresh());
    }

    public function destroy(string $uuid): JsonResponse
    {
        $user = $this->findOrFail($uuid);
        $deleted = $this->service->delete($user);

        if (!$deleted) {
            throw new RuntimeException('Failed to delete user.');
        }

        return response()->json(status: 204);
    }

    protected function findOrFail(string $uuid)
    {
        $user = $this->service->findByUuid($uuid);

        if (!$user) {
            throw new NotFoundHttpException('User not found.');
        }

        return $user;
    }
}
