<?php

namespace App\Modules\Acquirer\Alelo\Providers;

use App\Modules\Acquirer\Alelo\Parser\AleloParser;
use App\Modules\Acquirer\Core\Services\ParserRegistry;
use Illuminate\Support\ServiceProvider;

class AleloServiceProvider extends ServiceProvider
{
    public function boot(ParserRegistry $registry): void
    {
        // Registers the Alelo Parser into the central Acquirer Parser Registry
        $registry->register(new AleloParser());
    }
}
