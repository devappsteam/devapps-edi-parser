<?php

namespace App\Modules\Acquirer\Core\Providers;

use App\Modules\Acquirer\Core\Repositories\Contracts\IngestionRepositoryInterface;
use App\Modules\Acquirer\Core\Repositories\IngestionRepository;
use Illuminate\Support\ServiceProvider;

use App\Modules\Acquirer\Core\Services\ParserRegistry;

class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ParserRegistry::class);
        $this->app->bind(IngestionRepositoryInterface::class, IngestionRepository::class);
    }

    public function boot(): void
    {
        // Core services booting...
    }
}
