<?php

use App\Modules\Acquirer\Core\Http\Controllers\IngestionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes for Acquirer Core Module
|--------------------------------------------------------------------------
| These routes are loaded by the ModuleLoader and are assigned the "api"
| middleware group automatically. Prefix defaults to /api/v1/acquirer
*/

Route::prefix('acquirer')->group(function () {
    Route::post('ingestions', [IngestionController::class, 'upload']);
    Route::get('ingestions/{uuid}', [IngestionController::class, 'show']);
    Route::get('ingestions/{uuid}/records', [IngestionController::class, 'records']);
});
