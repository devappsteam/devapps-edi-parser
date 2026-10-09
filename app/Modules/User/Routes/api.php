<?php

use App\Modules\User\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::apiResource('users', UserController::class)
    ->parameters(['users' => 'uuid']);
