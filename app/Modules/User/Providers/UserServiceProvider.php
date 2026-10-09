<?php

namespace App\Modules\User\Providers;

use App\Modules\User\Models\User;
use App\Modules\User\Policies\UserPolicy;
use App\Modules\User\Repositories\Contracts\UserRepositoryInterface;
use App\Modules\User\Repositories\UserRepository;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class UserServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
    }

    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);
    }
}
