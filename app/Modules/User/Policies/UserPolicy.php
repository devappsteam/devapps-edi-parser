<?php

namespace App\Modules\User\Policies;

use App\Modules\User\Models\User;

class UserPolicy
{
    public function viewAny($user = null): bool
    {
        return true;
    }

    public function view($user, User $user): bool
    {
        return true;
    }

    public function create($user = null): bool
    {
        return true;
    }

    public function update($user, User $user): bool
    {
        return true;
    }

    public function delete($user, User $user): bool
    {
        return true;
    }

    public function restore($user, User $user): bool
    {
        return true;
    }

    public function forceDelete($user, User $user): bool
    {
        return true;
    }
}
